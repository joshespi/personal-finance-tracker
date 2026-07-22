<?php

namespace App\Console\Commands;

use App\Mail\ScheduledTransactionsSummary;
use App\Models\ScheduledTransaction;
use App\Models\User;
use App\Services\ScheduledTransactionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Daily backstop: MaterializeDueScheduledTransactions middleware already materializes
 * on every authenticated web request, so by the time this runs there's usually nothing
 * left for an active user — this command mainly catches accounts that never opened the
 * app. It remains the *only* trigger for the ScheduledTransactionsSummary email
 * (deliberately not sent from the middleware, or an active user would get emailed on
 * every page load about things they're already looking at).
 */
class MaterializeScheduledTransactions extends Command
{
    protected $signature = 'transactions:materialize';

    protected $description = 'Materialize due scheduled transactions for all users and notify them by email';

    public function handle(ScheduledTransactionService $service): int
    {
        $userIds = ScheduledTransaction::where('is_active', true)
            ->where('next_due_at', '<=', today())
            ->distinct()
            ->pluck('user_id');

        if ($userIds->isEmpty()) {
            $this->info('No scheduled transactions due today.');

            return self::SUCCESS;
        }

        $totalUsers        = 0;
        $totalTransactions = 0;

        User::whereIn('id', $userIds)->each(function (User $user) use ($service, &$totalUsers, &$totalTransactions) {
            $fired = $service->materializeForUser($user);

            if ($fired->isEmpty()) {
                return;
            }

            $totalUsers++;
            $totalTransactions += $fired->count();

            if (! $user->notify_scheduled_transactions) {
                $this->line("  {$user->email}: {$fired->count()} transaction(s) materialized (email muted)");

                return;
            }

            try {
                Mail::to($user->email)->send(new ScheduledTransactionsSummary($user, $fired));
                $this->line("  {$user->email}: {$fired->count()} transaction(s) materialized");
            } catch (\Throwable $e) {
                $this->warn("  Could not send summary email to {$user->email}: {$e->getMessage()}");
                report($e);
            }
        });

        $this->info("Done — {$totalTransactions} scheduled transaction(s) materialized for {$totalUsers} user(s).");

        return self::SUCCESS;
    }
}
