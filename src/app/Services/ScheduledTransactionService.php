<?php

namespace App\Services;

use App\Enums\Recurrence;
use App\Enums\ScheduledTransactionType;
use App\Models\LiabilityBalance;
use App\Models\ScheduledTransaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
            ScheduledTransactionType::EnvelopeFund    => $this->envelopeFund($scheduled, $date, $cleared),
            ScheduledTransactionType::EnvelopeSpend   => $this->envelopeSpend($scheduled, $date, $cleared),
            ScheduledTransactionType::CashDeposit     => $this->cashDeposit($scheduled, $date, $cleared),
            ScheduledTransactionType::CashWithdrawal  => $this->cashWithdrawal($scheduled, $date, $cleared),
            ScheduledTransactionType::MortgagePayment => $this->mortgagePayment($scheduled, $date, $cleared),
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
        $this->cashEntry($s, $date, $cleared, 'withdrawal', $s->envelope_id);
    }

    private function cashDeposit(ScheduledTransaction $s, Carbon $date, bool $cleared): void
    {
        $this->cashEntry($s, $date, $cleared, 'deposit');
    }

    private function cashWithdrawal(ScheduledTransaction $s, Carbon $date, bool $cleared): void
    {
        $this->cashEntry($s, $date, $cleared, 'withdrawal');
    }

    private function mortgagePayment(ScheduledTransaction $s, Carbon $date, bool $cleared): void
    {
        $this->cashEntry($s, $date, $cleared, 'withdrawal', $s->envelope_id);

        if ($s->liability) {
            $liability  = $s->liability;
            $balance    = $liability->currentBalance();
            $piPayment  = (float) ($liability->minimum_payment ?? 0);
            $interest   = round($liability->monthlyInterest(), 2);
            $principal  = max(0.0, round($piPayment - $interest, 2));
            $newBalance = round(max(0.0, $balance - $principal), 2);

            LiabilityBalance::create([
                'liability_id' => $liability->id,
                'balance'      => $newBalance,
                'recorded_at'  => $date->toDateString(),
            ]);
        }
    }

    private function advance(Carbon $date, ?Recurrence $recurrence): Carbon
    {
        return ($recurrence ?? Recurrence::Monthly)->advance($date);
    }

    private function cashEntry(ScheduledTransaction $s, Carbon $date, bool $cleared, string $type, ?int $envelopeId = null): void
    {
        // A schedule can lack a cash account (e.g. a liability with a payment day but
        // no payment account). Skip the ledger entry rather than fatal the whole
        // materialization run — one bad row must not take down every ledger page.
        if ($s->cashAccount === null) {
            Log::warning('Scheduled transaction has no cash account — ledger entry skipped', ['id' => $s->id]);

            return;
        }

        $s->cashAccount->transactions()->create([
            'type'        => $type,
            'envelope_id' => $envelopeId,
            'amount'      => $s->amount,
            'description' => $s->description,
            'occurred_at' => $date,
            'cleared'     => $cleared,
        ]);
    }
}
