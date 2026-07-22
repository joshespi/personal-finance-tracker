<?php

namespace App\Models;

use App\Enums\DashboardWidget;
use App\Enums\EmailSummaryFrequency;
use App\Enums\EmailSummarySection;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

#[Fillable(['name', 'email', 'password', 'is_admin', 'emergency_fund_target_months', 'target_stock_pct', 'target_crypto_pct', 'target_real_estate_pct', 'target_bond_pct', 'notify_scheduled_transactions', 'dashboard_preferences', 'email_summary_frequencies', 'email_summary_sections', 'last_email_summary_sent_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at'             => 'datetime',
            'password'                      => 'hashed',
            'is_admin'                      => 'boolean',
            'emergency_fund_target_months'  => 'integer',
            'target_stock_pct'              => 'decimal:2',
            'target_crypto_pct'             => 'decimal:2',
            'target_real_estate_pct'        => 'decimal:2',
            'target_bond_pct'               => 'decimal:2',
            'notify_scheduled_transactions' => 'boolean',
            'dashboard_preferences'         => 'array',
            'email_summary_frequencies'     => 'array',
            'email_summary_sections'        => 'array',
            'last_email_summary_sent_at'    => 'datetime',
        ];
    }

    /**
     * Whether a given dashboard block should render for this user. Widgets are
     * visible by default — only an explicit false in dashboard_preferences hides
     * one, so newly-added widgets show up for existing users automatically.
     */
    public function showsWidget(DashboardWidget|string $widget): bool
    {
        $key = $widget instanceof DashboardWidget ? $widget->value : $widget;

        return (bool) ($this->dashboard_preferences[$key] ?? true);
    }

    /** Whether this user opted into a given email-summary cadence. Off by default (opt-in). */
    public function wantsEmailFrequency(EmailSummaryFrequency|string $frequency): bool
    {
        $value = $frequency instanceof EmailSummaryFrequency ? $frequency->value : $frequency;

        return in_array($value, $this->email_summary_frequencies ?? [], true);
    }

    /** Whether this user opted into a given email-summary content section. Off by default (opt-in). */
    public function wantsEmailSummarySection(EmailSummarySection|string $section): bool
    {
        $value = $section instanceof EmailSummarySection ? $section->value : $section;

        return in_array($value, $this->email_summary_sections ?? [], true);
    }

    public function portfolios(): HasMany
    {
        return $this->hasMany(Portfolio::class);
    }

    public function assetClassifications(): HasMany
    {
        return $this->hasMany(UserAssetClassification::class);
    }

    // Applies this user's per-asset type overrides onto the given Asset models in place
    // (allocation + display only — the global Asset.asset_type that drives the price feed is untouched).
    public function applyAssetClassifications(Collection $assets): void
    {
        $ids = $assets->pluck('id')->filter()->all();
        if (empty($ids)) {
            return;
        }

        $overrides = $this->assetClassifications()->whereIn('asset_id', $ids)->pluck('asset_type', 'asset_id');

        foreach ($assets as $asset) {
            if ($asset && $overrides->has($asset->id)) {
                $asset->asset_type = $overrides->get($asset->id);
            }
        }
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

    public function pensions(): HasMany
    {
        return $this->hasMany(Pension::class);
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

    public function incomeCategories(): HasMany
    {
        return $this->hasMany(IncomeCategory::class);
    }

    public function cashDeposits(): Builder
    {
        return CashTransaction::query()
            ->join('cash_accounts', 'cash_accounts.id', '=', 'cash_transactions.cash_account_id')
            ->where('cash_accounts.user_id', $this->id)
            ->where('cash_transactions.type', 'deposit');
    }

    /**
     * Total deposits over the last $months *complete* calendar months (the
     * in-progress month is excluded so a mid-month call doesn't dilute the
     * average). The one definition of "trailing income" shared by the forecast
     * and retirement projections.
     */
    public function incomeForTrailingMonths(int $months): float
    {
        return (float) $this->cashDeposits()
            ->whereBetween('cash_transactions.occurred_at', [
                now()->subMonths($months)->startOfMonth(),
                now()->subMonth()->endOfMonth(),
            ])
            ->sum('cash_transactions.amount');
    }

    /**
     * The spend-side mirror of incomeForTrailingMonths() — total envelope-tagged
     * withdrawals over the last $months *complete* calendar months, same
     * complete-months convention, so "monthly income" and "monthly spend" stay
     * comparable wherever both are used together (e.g. a savings-rate calculation).
     */
    public function spendForTrailingMonths(int $months): float
    {
        return (float) CashTransaction::query()
            ->join('envelopes', 'envelopes.id', '=', 'cash_transactions.envelope_id')
            ->where('envelopes.user_id', $this->id)
            ->where('cash_transactions.type', 'withdrawal')
            ->whereBetween('cash_transactions.occurred_at', [
                now()->subMonths($months)->startOfMonth(),
                now()->subMonth()->endOfMonth(),
            ])
            ->sum('cash_transactions.amount');
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
            $query = CashTransaction::query()
                ->join('cash_accounts', 'cash_accounts.id', '=', 'cash_transactions.cash_account_id')
                ->where('cash_accounts.user_id', $this->id);

            $this->totalCashCache = CashTransaction::balanceTotals($query)['working'];
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

    /** Sum of (market_value + manual_value) across each portfolio's most recent snapshot. */
    public function latestPortfolioValue(): float
    {
        $portfolioIds = $this->portfolios()->pluck('id');

        if ($portfolioIds->isEmpty()) {
            return 0.0;
        }

        $latestDates = PortfolioSnapshot::whereIn('portfolio_id', $portfolioIds)
            ->selectRaw('portfolio_id, MAX(recorded_on) as max_date')
            ->groupBy('portfolio_id');

        return (float) PortfolioSnapshot::joinSub($latestDates, 'latest', fn ($j) => $j
            ->on('portfolio_snapshots.portfolio_id', '=', 'latest.portfolio_id')
            ->on('portfolio_snapshots.recorded_on', '=', 'latest.max_date')
        )->selectRaw('SUM(portfolio_snapshots.market_value + portfolio_snapshots.manual_value) as total')
            ->value('total');
    }
}
