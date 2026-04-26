<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ManualAsset extends Model
{
    use HasFactory;

    protected $fillable = ['portfolio_id', 'name', 'description', 'asset_class', 'currency'];

    public function portfolio(): BelongsTo
    {
        return $this->belongsTo(Portfolio::class);
    }

    public function valuations(): HasMany
    {
        return $this->hasMany(ManualValuation::class);
    }

    public function latestValuation(): HasOne
    {
        return $this->hasOne(ManualValuation::class)->latestOfMany('valued_at');
    }

    public function liabilities(): HasMany
    {
        return $this->hasMany(Liability::class);
    }
}
