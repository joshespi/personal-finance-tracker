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
        $dueQuery = fn () => $user->scheduledTransactions()
            ->where('is_active', true)
            ->where('next_due_at', '<=', today());

        // Cheap unlocked probe first: this runs on every authenticated request (see
        // MaterializeDueScheduledTransactions), and the overwhelming majority find nothing
        // due — opening a write transaction each time to discover that would be pure cost.
        // exists() rather than get(), so the common no-op path never hydrates a model.
        if (! $dueQuery()->exists()) {
            return collect();
        }

        return DB::transaction(function () use ($dueQuery) {
            // Re-read under a row lock. Without it, two concurrent requests from the same
            // user (a page load racing a Livewire poll) both see the same due row and both
            // materialize it, double-posting the transaction and double-advancing nothing —
            // next_due_at is written from whichever finishes last. The lock makes the second
            // request wait and re-read a row that is no longer due.
            $due = $dueQuery()
                ->with(['envelope', 'cashAccount', 'liability.latestBalance'])
                ->lockForUpdate()
                ->get();

            foreach ($due as $scheduled) {
                $this->materializeOne($scheduled);
            }

            return $due;
        });
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
            ScheduledTransactionType::EnvelopeFund     => $this->envelopeFund($scheduled, $date, $cleared),
            ScheduledTransactionType::EnvelopeSpend    => $this->envelopeSpend($scheduled, $date, $cleared),
            ScheduledTransactionType::CashDeposit      => $this->cashDeposit($scheduled, $date, $cleared),
            ScheduledTransactionType::CashWithdrawal   => $this->cashWithdrawal($scheduled, $date, $cleared),
            ScheduledTransactionType::LiabilityPayment => $this->liabilityPayment($scheduled, $date, $cleared),
        };
    }

    private function envelopeFund(ScheduledTransaction $s, Carbon $date, bool $cleared): void
    {
        // envelope_id is ON DELETE SET NULL, so an envelope_fund schedule can outlive its
        // envelope. Same skip-don't-fatal rule as cashEntry()'s missing-account guard —
        // materialization runs on every authenticated request, so one bad row throwing
        // here would lock the user out of every page.
        if ($s->envelope === null) {
            Log::warning('Scheduled transaction has no envelope — materialization skipped', ['id' => $s->id]);

            return;
        }

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
        $this->cashEntry($s, $date, $cleared, 'withdrawal', $this->ownedEnvelopeId($s));
    }

    /**
     * The schedule's envelope, but only when it belongs to the same user as the schedule.
     *
     * Validation is the real gate; this is the backstop for rows written before that gate
     * existed. A withdrawal tagged with a foreign envelope_id debits the *other* user's
     * envelope — Envelope::spendTransactions() joins on envelope_id alone — so an untrusted
     * value has to be dropped here rather than carried into the ledger.
     */
    private function ownedEnvelopeId(ScheduledTransaction $s): ?int
    {
        if ($s->envelope_id === null) {
            return null;
        }

        return $s->envelope?->user_id === $s->user_id ? $s->envelope_id : null;
    }

    private function cashDeposit(ScheduledTransaction $s, Carbon $date, bool $cleared): void
    {
        $this->cashEntry($s, $date, $cleared, 'deposit');
    }

    private function cashWithdrawal(ScheduledTransaction $s, Carbon $date, bool $cleared): void
    {
        $this->cashEntry($s, $date, $cleared, 'withdrawal');
    }

    private function liabilityPayment(ScheduledTransaction $s, Carbon $date, bool $cleared): void
    {
        $this->cashEntry($s, $date, $cleared, 'withdrawal', $this->ownedEnvelopeId($s));

        if ($s->liability) {
            $liability = $s->liability;
            $balance   = $liability->currentBalance();
            // Principal comes off the payment that was actually debited above ($s->amount),
            // not off minimum_payment — the two are user-editable independently, and taking
            // cash out at one figure while paying the balance down at another let the ledger
            // and the balance history disagree.
            $piPayment = $liability->principalAndInterestPortion((float) $s->amount);
            $interest  = round($liability->monthlyInterest(), 2);
            // Negative when the payment doesn't cover interest — balance grows (negative amortization)
            // instead of silently freezing, which matters once any liability type can be scheduled here.
            $principal  = round($piPayment - $interest, 2);
            $newBalance = round(max(0.0, $balance - $principal), 2);

            $balanceRow = LiabilityBalance::create([
                'liability_id' => $liability->id,
                'balance'      => $newBalance,
                'recorded_at'  => $date->toDateString(),
            ]);

            // materializeOne()'s while loop calls this repeatedly for the same $liability
            // instance on multi-cycle catch-up — keep the cached relation in sync so the
            // next cycle's currentBalance()/monthlyInterest() compound off this payment
            // instead of the stale balance loaded before the loop began.
            $liability->setRelation('latestBalance', $balanceRow);
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
