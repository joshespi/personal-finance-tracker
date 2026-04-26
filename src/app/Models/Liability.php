<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Liability extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'manual_asset_id', 'name', 'liability_type', 'interest_rate', 'notes', 'currency'];

    protected $casts = [
        'interest_rate' => 'decimal:3',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function manualAsset(): BelongsTo
    {
        return $this->belongsTo(ManualAsset::class);
    }

    public function balances(): HasMany
    {
        return $this->hasMany(LiabilityBalance::class);
    }

    public function latestBalance(): HasOne
    {
        return $this->hasOne(LiabilityBalance::class)->latestOfMany('recorded_at');
    }
}
