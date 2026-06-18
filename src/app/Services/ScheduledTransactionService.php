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

    /**
     * Fire the next occurrence immediately — e.g. it happened earlier than scheduled —
     * then advance the schedule one cycle. The transaction is dated $date (today by default).
     */
    public function enterNow(ScheduledTransaction $scheduled, ?Carbon $date = null): void
    {
        $scheduled->loadMissing(['envelope', 'cashAccount', 'liability.latestBalance']);

        DB::transaction(function () use ($scheduled, $date) {
            // "Enter now" is a deliberate user action — record it as cleared.
            $this->createTransactions($scheduled, $date ?? today(), cleared: true);
            $this->advanceCycle($scheduled);
        });
    }

    /** Skip the next occurrence without recording anything, advancing one cycle. */
    public function skipNext(ScheduledTransaction $scheduled): void
    {
        $this->advanceCycle($scheduled);
    }

    /** Move the schedule forward one cycle and persist. */
    private function advanceCycle(ScheduledTransaction $scheduled): void
    {
        $scheduled->next_due_at = $this->advance($scheduled->next_due_at, $scheduled->recurrence);
        $scheduled->save();
    }

    private function materializeOne(ScheduledTransaction $scheduled): int
    {
        $date    = $scheduled->next_due_at->copy();
        $today   = today();
        $created = 0;
        $cap     = 24;

        while ($date->lte($today) && $created < $cap) {
            // Auto-materialized occurrences haven't posted to the bank yet — enter them pending.
            $this->createTransactions($scheduled, $date, cleared: false);
            $date = $this->advance($date, $scheduled->recurrence);
            $created++;
        }

        $scheduled->next_due_at = $date;
        $scheduled->save();

        return $created;
    }

    private function createTransactions(ScheduledTransaction $scheduled, Carbon $date, bool $cleared): void
    {
        match ($scheduled->type) {
            'envelope_fund'    => $this->envelopeFund($scheduled, $date, $cleared),
            'envelope_spend'   => $this->envelopeSpend($scheduled, $date, $cleared),
            'cash_deposit'     => $this->cashDeposit($scheduled, $date, $cleared),
            'cash_withdrawal'  => $this->cashWithdrawal($scheduled, $date, $cleared),
            'mortgage_payment' => $this->mortgagePayment($scheduled, $date, $cleared),
        };
    }

    private function envelopeFund(ScheduledTransaction $s, Carbon $date, bool $cleared): void
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
                'description' => 'Funded envelope: '.$s->envelope->name,
                'occurred_at' => $date,
                'cleared'     => $cleared,
            ]);
        }
    }

    private function envelopeSpend(ScheduledTransaction $s, Carbon $date, bool $cleared): void
    {
        $s->cashAccount->transactions()->create([
            'type'        => 'withdrawal',
            'envelope_id' => $s->envelope_id,
            'amount'      => $s->amount,
            'description' => $s->description,
            'occurred_at' => $date,
            'cleared'     => $cleared,
        ]);
    }

    private function cashDeposit(ScheduledTransaction $s, Carbon $date, bool $cleared): void
    {
        $s->cashAccount->transactions()->create([
            'type'        => 'deposit',
            'amount'      => $s->amount,
            'description' => $s->description,
            'occurred_at' => $date,
            'cleared'     => $cleared,
        ]);
    }

    private function cashWithdrawal(ScheduledTransaction $s, Carbon $date, bool $cleared): void
    {
        $s->cashAccount->transactions()->create([
            'type'        => 'withdrawal',
            'amount'      => $s->amount,
            'description' => $s->description,
            'occurred_at' => $date,
            'cleared'     => $cleared,
        ]);
    }

    private function mortgagePayment(ScheduledTransaction $s, Carbon $date, bool $cleared): void
    {
        $s->cashAccount->transactions()->create([
            'type'        => 'withdrawal',
            'envelope_id' => $s->envelope_id,
            'amount'      => $s->amount,
            'description' => $s->description,
            'occurred_at' => $date,
            'cleared'     => $cleared,
        ]);

        if ($s->liability) {
            $liability  = $s->liability;
            $balance    = $liability->currentBalance();
            $rate       = (float) ($liability->interest_rate ?? 0);
            $piPayment  = (float) ($liability->minimum_payment ?? 0);
            $interest   = $rate > 0 ? round($balance * $rate / 100 / 12, 2) : 0.0;
            $principal  = max(0.0, round($piPayment - $interest, 2));
            $newBalance = round(max(0.0, $balance - $principal), 2);

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
