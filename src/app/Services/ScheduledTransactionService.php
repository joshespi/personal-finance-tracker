<?php

namespace App\Services;

use App\Models\LiabilityBalance;
use App\Models\ScheduledTransaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ScheduledTransactionService
{
    /** Materialize all due transactions for a user. Returns the ScheduledTransaction models that fired. */
    public function materializeForUser(User $user): Collection
    {
        $due = $user->scheduledTransactions()
            ->where('is_active', true)
            ->where('next_due_at', '<=', today())
            ->with(['envelope', 'cashAccount', 'liability.latestBalance'])
            ->get();

        DB::transaction(function () use ($due) {
            foreach ($due as $scheduled) {
                $this->materializeOne($scheduled);
            }
        });

        return $due;
    }

    private function materializeOne(ScheduledTransaction $scheduled): int
    {
        $date    = $scheduled->next_due_at->copy();
        $today   = today();
        $created = 0;
        $cap     = 24;

        while ($date->lte($today) && $created < $cap) {
            $this->createTransactions($scheduled, $date);
            $date = $this->advance($date, $scheduled->recurrence);
            $created++;
        }

        $scheduled->next_due_at = $date;
        $scheduled->save();

        return $created;
    }

    private function createTransactions(ScheduledTransaction $scheduled, Carbon $date): void
    {
        match ($scheduled->type) {
            'envelope_fund'    => $this->envelopeFund($scheduled, $date),
            'envelope_spend'   => $this->envelopeSpend($scheduled, $date),
            'cash_deposit'     => $this->cashDeposit($scheduled, $date),
            'cash_withdrawal'  => $this->cashWithdrawal($scheduled, $date),
            'mortgage_payment' => $this->mortgagePayment($scheduled, $date),
        };
    }

    private function envelopeFund(ScheduledTransaction $s, Carbon $date): void
    {
        $s->envelope->transactions()->create([
            'type'        => 'fund',
            'amount'      => $s->amount,
            'description' => $s->description,
            'occurred_at' => $date,
        ]);

        if ($s->cash_account_id && $s->cashAccount) {
            $s->cashAccount->transactions()->create([
                'type'        => 'withdrawal',
                'amount'      => $s->amount,
                'description' => 'Funded envelope: ' . $s->envelope->name,
                'occurred_at' => $date,
            ]);
        }
    }

    private function envelopeSpend(ScheduledTransaction $s, Carbon $date): void
    {
        $s->cashAccount->transactions()->create([
            'type'        => 'withdrawal',
            'envelope_id' => $s->envelope_id,
            'amount'      => $s->amount,
            'description' => $s->description,
            'occurred_at' => $date,
        ]);
    }

    private function cashDeposit(ScheduledTransaction $s, Carbon $date): void
    {
        $s->cashAccount->transactions()->create([
            'type'        => 'deposit',
            'amount'      => $s->amount,
            'description' => $s->description,
            'occurred_at' => $date,
        ]);
    }

    private function cashWithdrawal(ScheduledTransaction $s, Carbon $date): void
    {
        $s->cashAccount->transactions()->create([
            'type'        => 'withdrawal',
            'amount'      => $s->amount,
            'description' => $s->description,
            'occurred_at' => $date,
        ]);
    }

    private function mortgagePayment(ScheduledTransaction $s, Carbon $date): void
    {
        $s->cashAccount->transactions()->create([
            'type'        => 'withdrawal',
            'envelope_id' => $s->envelope_id,
            'amount'      => $s->amount,
            'description' => $s->description,
            'occurred_at' => $date,
        ]);

        if ($s->liability) {
            $liability   = $s->liability;
            $balance     = $liability->currentBalance();
            $rate        = (float) ($liability->interest_rate ?? 0);
            $piPayment   = (float) ($liability->minimum_payment ?? 0);
            $interest    = $rate > 0 ? round($balance * $rate / 100 / 12, 2) : 0.0;
            $principal   = max(0.0, round($piPayment - $interest, 2));
            $newBalance  = round(max(0.0, $balance - $principal), 2);

            LiabilityBalance::create([
                'liability_id' => $liability->id,
                'balance'      => $newBalance,
                'recorded_at'  => $date->toDateString(),
            ]);
        }
    }

    private function advance(Carbon $date, string $recurrence): Carbon
    {
        return match ($recurrence) {
            'weekly'   => $date->copy()->addWeek(),
            'biweekly' => $date->copy()->addWeeks(2),
            default    => $date->copy()->addMonth(),
        };
    }
}
