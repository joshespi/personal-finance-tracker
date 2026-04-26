<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetPrice extends Model
{
    use HasFactory;

    protected $fillable = ['asset_id', 'price', 'currency', 'recorded_at'];

    protected $casts = [
        'recorded_at' => 'datetime',
        'price'        => 'decimal:8',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
