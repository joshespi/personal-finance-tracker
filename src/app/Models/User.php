<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'is_admin', 'emergency_fund_target_months', 'target_stock_pct', 'target_crypto_pct', 'target_real_estate_pct', 'target_bond_pct', 'notify_scheduled_transactions'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at'            => 'datetime',
            'password'                     => 'hashed',
            'is_admin'                     => 'boolean',
            'emergency_fund_target_months' => 'integer',
            'target_stock_pct'             => 'integer',
            'target_crypto_pct'            => 'integer',
            'target_real_estate_pct'       => 'integer',
            'target_bond_pct'              => 'integer',
            'notify_scheduled_transactions' => 'boolean',
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

    public function cashDeposits(): Builder
    {
        return CashTransaction::query()
            ->join('cash_accounts', 'cash_accounts.id', '=', 'cash_transactions.cash_account_id')
            ->where('cash_accounts.user_id', $this->id)
            ->where('cash_transactions.type', 'deposit');
    }

    public function readyToAssign(): float
    {
        $funded = (float) EnvelopeTransaction::query()
            ->join('envelopes', 'envelopes.id', '=', 'envelope_transactions.envelope_id')
            ->where('envelopes.user_id', $this->id)
            ->where('envelope_transactions.type', 'fund')
            ->sum('envelope_transactions.amount');

        $spent = (float) CashTransaction::query()
            ->join('envelopes', 'envelopes.id', '=', 'cash_transactions.envelope_id')
            ->where('envelopes.user_id', $this->id)
            ->where('cash_transactions.type', 'withdrawal')
            ->sum('cash_transactions.amount');

        return round($this->totalCash() - ($funded - $spent), 2);
    }

    private ?float $totalCashCache = null;

    public function totalCash(): float
    {
        if ($this->totalCashCache === null) {
            $this->totalCashCache = (float) CashTransaction::query()
                ->join('cash_accounts', 'cash_accounts.id', '=', 'cash_transactions.cash_account_id')
                ->where('cash_accounts.user_id', $this->id)
                ->selectRaw("COALESCE(SUM(CASE WHEN cash_transactions.type = 'deposit' THEN cash_transactions.amount ELSE -cash_transactions.amount END), 0) AS bal")
                ->value('bal');
        }
        return $this->totalCashCache;
    }

    private ?float $totalDebtCache = null;

    public function totalDebt(): float
    {
        if ($this->totalDebtCache === null) {
            $this->totalDebtCache = (float) $this->liabilities()
                ->with('latestBalance')
                ->get()
                ->sum(fn ($l) => $l->currentBalance());
        }
        return $this->totalDebtCache;
    }
}
