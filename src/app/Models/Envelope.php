<?php

namespace App\Models;

use App\Concerns\HasOwnershipRule;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Envelope extends Model
{
    use HasFactory, HasOwnershipRule;

    public const CATEGORY_ORDER = ['Emergency Fund', 'Mandatory', 'Wealth Building', 'Spending'];

    protected $fillable = ['user_id', 'cash_account_id', 'name', 'monthly_target', 'goal_amount', 'goal_date', 'color', 'sort_order', 'notes', 'is_mandatory', 'include_in_emergency_fund', 'is_emergency_fund', 'is_savings'];

    public function category(): string
    {
        return match (true) {
            $this->is_emergency_fund => 'Emergency Fund',
            $this->is_mandatory      => 'Mandatory',
            $this->is_savings        => 'Wealth Building',
            default                  => 'Spending',
        };
    }

    protected $casts = [
        'monthly_target'            => 'decimal:8',
        'goal_amount'               => 'decimal:2',
        'goal_date'                 => 'date',
        'is_mandatory'              => 'boolean',
        'include_in_emergency_fund' => 'boolean',
        'is_emergency_fund'         => 'boolean',
        'is_savings'                => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The physical account this envelope's balance is meant to live in (optional). */
    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(EnvelopeTransaction::class);
    }

    public function spendTransactions(): HasMany
    {
        return $this->hasMany(CashTransaction::class)->withdrawals();
    }

    public function scopeWithBalanceTotals($query)
    {
        return $query
            ->withSum(['transactions as funds_total' => fn ($q) => $q->where('type', 'fund')], 'amount')
            ->withSum('spendTransactions as spends_total', 'amount');
    }

    /**
     * Aggregate `month_fund_total`: how much was assigned ('fund') within the
     * given month. The single definition of "assigned this month" — the budget
     * grid and the inline-assign endpoint must agree on it, since the grid seeds
     * inputs from it and assignOne diffs the desired total against it.
     *
     * $as renames the aggregate so one query can carry two months at once (the
     * copy-previous-month action needs source and target side by side).
     */
    public function scopeWithMonthFundTotal($query, CarbonInterface $month, string $as = 'month_fund_total')
    {
        return $query->withSum([
            "transactions as {$as}" => fn ($q) => $q
                ->where('type', 'fund')
                ->whereBetween('occurred_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()]),
        ], 'amount');
    }

    public function balance(): float
    {
        $attrs = $this->getAttributes();
        if (array_key_exists('funds_total', $attrs) && array_key_exists('spends_total', $attrs)) {
            return (float) ($this->funds_total ?? 0) - (float) ($this->spends_total ?? 0);
        }

        if ($this->relationLoaded('transactions') && $this->relationLoaded('spendTransactions')) {
            $funded = $this->transactions->where('type', 'fund')->sum('amount');
            $spent  = $this->spendTransactions->sum('amount');

            return (float) $funded - (float) $spent;
        }

        $funded = (float) $this->transactions()->where('type', 'fund')->sum('amount');
        $spent  = (float) $this->spendTransactions()->sum('amount');

        return $funded - $spent;
    }

    public function spentInMonth(?CarbonInterface $month = null): float
    {
        $month ??= now();
        $start = $month->copy()->startOfMonth();
        $end   = $month->copy()->endOfMonth();

        if ($this->relationLoaded('spendTransactions')) {
            return (float) $this->spendTransactions
                ->filter(fn ($t) => $t->occurred_at >= $start && $t->occurred_at <= $end)
                ->sum('amount');
        }

        return (float) $this->spendTransactions()
            ->whereBetween('occurred_at', [$start, $end])
            ->sum('amount');
    }

    public function toBackupArray(): array
    {
        return [
            'name'              => $this->name,
            'monthly_target'    => $this->monthly_target !== null ? (float) $this->monthly_target : null,
            'goal_amount'       => $this->goal_amount !== null ? (float) $this->goal_amount : null,
            'goal_date'         => $this->goal_date?->toDateString(),
            'color'             => $this->color,
            'sort_order'        => $this->sort_order,
            'is_mandatory'      => (bool) $this->is_mandatory,
            'is_emergency_fund' => (bool) $this->is_emergency_fund,
            'is_savings'        => (bool) $this->is_savings,
            'notes'             => $this->notes,
            'transactions'      => $this->transactions->map(fn ($t) => $t->toBackupArray())->values(),
        ];
    }
}
