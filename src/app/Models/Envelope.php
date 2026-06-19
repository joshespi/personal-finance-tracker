<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Envelope extends Model
{
    use HasFactory;

    public const CATEGORY_ORDER = ['Emergency Fund', 'Mandatory', 'Wealth Building', 'Spending'];

    protected $fillable = ['user_id', 'name', 'monthly_target', 'goal_amount', 'goal_date', 'color', 'sort_order', 'notes', 'is_mandatory', 'is_emergency_fund', 'is_savings'];

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
        'monthly_target'    => 'decimal:8',
        'goal_amount'       => 'decimal:2',
        'goal_date'         => 'date',
        'is_mandatory'      => 'boolean',
        'is_emergency_fund' => 'boolean',
        'is_savings'        => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(EnvelopeTransaction::class);
    }

    public function spendTransactions(): HasMany
    {
        return $this->hasMany(CashTransaction::class)->withdrawals();
    }

    public function balance(): float
    {
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
}
