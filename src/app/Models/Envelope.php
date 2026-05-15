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

    protected $fillable = ['user_id', 'name', 'monthly_target', 'goal_amount', 'goal_date', 'color', 'sort_order', 'notes', 'is_mandatory', 'is_emergency_fund', 'is_savings'];

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

    public function balance(): float
    {
        if ($this->relationLoaded('transactions')) {
            return (float) $this->transactions->sum(
                fn ($t) => $t->type === 'fund' ? (float) $t->amount : -(float) $t->amount
            );
        }

        return (float) $this->transactions()
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'fund' THEN amount ELSE -amount END), 0) AS bal")
            ->value('bal');
    }

    public function spentInMonth(?CarbonInterface $month = null): float
    {
        $month ??= now();

        return (float) $this->transactions()
            ->where('type', 'spend')
            ->whereBetween('occurred_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
            ->sum('amount');
    }
}
