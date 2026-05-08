<?php

namespace App\Services;

use App\Models\ScheduledTransaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ScheduledTransactionService
{
    /** Materialize all due transactions for a user. Returns count of transactions created. */
    public function materializeForUser(User $user): int
    {
        $due = $user->scheduledTransactions()
            ->where('is_active', true)
            ->where('next_due_at', '<=', today())
            ->with(['envelope', 'cashAccount'])
            ->get();

        $created = 0;

        DB::transaction(function () use ($due, &$created) {
            foreach ($due as $scheduled) {
                $created += $this->materializeOne($scheduled);
            }
        });

        return $created;
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
            'envelope_fund' => $this->envelopeFund($scheduled, $date),
            'envelope_spend' => $this->envelopeSpend($scheduled, $date),
            'cash_deposit' => $this->cashDeposit($scheduled, $date),
            'cash_withdrawal' => $this->cashWithdrawal($scheduled, $date),
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
        $s->envelope->transactions()->create([
            'type'        => 'spend',
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

    private function advance(Carbon $date, string $recurrence): Carbon
    {
        return match ($recurrence) {
            'weekly'   => $date->copy()->addWeek(),
            'biweekly' => $date->copy()->addWeeks(2),
            default    => $date->copy()->addMonth(),
        };
    }
}
