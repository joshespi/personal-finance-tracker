<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'is_admin'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'is_admin'          => 'boolean',
        ];
    }

    public function portfolios(): HasMany
    {
        return $this->hasMany(Portfolio::class);
    }

    public function watchlistItems(): HasMany
    {
        return $this->hasMany(WatchlistItem::class);
    }

    public function loginHistory(): HasMany
    {
        return $this->hasMany(LoginHistory::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function liabilities(): HasMany
    {
        return $this->hasMany(Liability::class);
    }

    public function cashAccounts(): HasMany
    {
        return $this->hasMany(CashAccount::class);
    }

    public function envelopes(): HasMany
    {
        return $this->hasMany(Envelope::class);
    }

    public function scheduledTransactions(): HasMany
    {
        return $this->hasMany(ScheduledTransaction::class);
    }

    public function incomeEntries(): HasMany
    {
        return $this->hasMany(IncomeEntry::class);
    }

    public function readyToAssign(): float
    {
        $totalIncome = (float) $this->incomeEntries()->sum('amount');

        $totalAssigned = (float) EnvelopeTransaction::query()
            ->join('envelopes', 'envelopes.id', '=', 'envelope_transactions.envelope_id')
            ->where('envelopes.user_id', $this->id)
            ->where('envelope_transactions.type', 'fund')
            ->sum('envelope_transactions.amount');

        return round($totalIncome - $totalAssigned, 2);
    }

    public function totalCash(): float
    {
        return (float) CashTransaction::query()
            ->join('cash_accounts', 'cash_accounts.id', '=', 'cash_transactions.cash_account_id')
            ->where('cash_accounts.user_id', $this->id)
            ->selectRaw("COALESCE(SUM(CASE WHEN cash_transactions.type = 'deposit' THEN cash_transactions.amount ELSE -cash_transactions.amount END), 0) AS bal")
            ->value('bal');
    }

    public function totalDebt(): float
    {
        return (float) $this->liabilities()
            ->with('latestBalance')
            ->get()
            ->sum(fn ($l) => $l->currentBalance());
    }
}
