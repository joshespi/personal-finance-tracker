<?php

namespace App\Models;

use App\Enums\AssetType;
use App\Enums\PriceSource;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Asset extends Model
{
    use HasFactory;

    protected $fillable = ['symbol', 'name', 'asset_type', 'price_source', 'exchange', 'coingecko_id', 'polygon_ticker'];

    public function effectivePriceSource(): PriceSource
    {
        if ($this->price_source) {
            return PriceSource::from($this->price_source);
        }

        return $this->asset_type === AssetType::Crypto->value ? PriceSource::CoinGecko : PriceSource::Finnhub;
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
