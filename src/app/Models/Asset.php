<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Asset extends Model
{
    protected $fillable = ['symbol', 'name', 'asset_type', 'exchange', 'coingecko_id', 'polygon_ticker'];

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(AssetPrice::class);
    }

    public function latestPrice(): HasOne
    {
        return $this->hasOne(AssetPrice::class)->latestOfMany('recorded_at');
    }
}
