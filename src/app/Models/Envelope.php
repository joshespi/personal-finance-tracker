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

    protected $fillable = ['user_id', 'name', 'monthly_target', 'color', 'sort_order', 'notes', 'is_mandatory', 'is_emergency_fund'];

    protected $casts = [
        'monthly_target'   => 'decimal:8',
        'is_mandatory'     => 'boolean',
        'is_emergency_fund' => 'boolean',
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
