<?php

namespace App\Console\Commands;

use App\Enums\EmailSummaryFrequency;
use App\Enums\EmailSummarySection;
use App\Mail\AccountChangesSummary;
use App\Models\User;
use App\Services\EmailSummaryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendAccountSummaries extends Command
{
    protected $signature = 'email:send-summaries';

    protected $description = 'Send the periodic account-summary email to users opted into a frequency due today';

    public function handle(EmailSummaryService $service): int
    {
        $dueFrequencies = collect(EmailSummaryFrequency::cases())->filter->isDueToday();

        if ($dueFrequencies->isEmpty()) {
            $this->info('No email-summary frequencies due today.');

            return self::SUCCESS;
        }

        // A user opted into both a subsumed cadence and the one it's subsumed by would
        // otherwise get two overlapping emails the day both are due — see
        // EmailSummaryFrequency::isSubsumedBy().
        $weeklyDueToday = $dueFrequencies->contains(EmailSummaryFrequency::Weekly);

        $sent = 0;

        foreach ($dueFrequencies as $frequency) {
            $users = User::whereJsonContains('email_summary_frequencies', $frequency->value)->get();

            foreach ($users as $user) {
                if ($weeklyDueToday
                    && $frequency->isSubsumedBy(EmailSummaryFrequency::Weekly)
                    && $user->wantsEmailFrequency(EmailSummaryFrequency::Weekly)) {
                    continue;
                }

                $sections = collect($user->email_summary_sections ?? [])
                    ->map(fn (string $value) => EmailSummarySection::tryFrom($value))
                    ->filter();

                if ($sections->isEmpty()) {
                    continue;
                }

                // Always derive the window from the frequency's own period rather than the
                // shared last_email_summary_sent_at column — a user with both Weekly and
                // Monthly enabled would otherwise have the second frequency processed each
                // run see the first frequency's just-written timestamp as its "since".
                $until = now();
                $since = $frequency->periodStart($until);

                try {
                    $data = $service->compute($user, $since, $until, $sections);
                    Mail::to($user->email)->send(new AccountChangesSummary($user, $frequency, $data, $since, $until));
                    $user->update(['last_email_summary_sent_at' => $until]);
                    $sent++;
                    $this->line("  {$user->email}: {$frequency->label()} summary sent");
                } catch (\Throwable $e) {
                    $this->warn("  Could not send {$frequency->label()} summary to {$user->email}: {$e->getMessage()}");
                    report($e);
                }
            }
        }

        $this->info("Done — {$sent} summary email(s) sent.");

        return self::SUCCESS;
    }
}
