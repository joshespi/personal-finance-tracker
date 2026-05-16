<?php

namespace App\Models;

use App\Enums\PriceSource;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Asset extends Model
{
    use HasFactory;

    protected $fillable = ['symbol', 'name', 'asset_type', 'price_source', 'exchange', 'coingecko_id', 'polygon_ticker'];

    public function effectivePriceSource(): string
    {
        return $this->price_source ?? ($this->asset_type === 'crypto' ? PriceSource::CoinGecko->value : PriceSource::Finnhub->value);
    }

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
