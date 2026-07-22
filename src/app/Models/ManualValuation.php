<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManualValuation extends Model
{
    use HasFactory;

    protected $fillable = ['manual_asset_id', 'value', 'notes', 'valued_at'];

    protected $casts = [
        'valued_at' => 'datetime',
        'value'     => 'decimal:8',
    ];

    public function manualAsset(): BelongsTo
    {
        return $this->belongsTo(ManualAsset::class);
    }

    public function toBackupArray(): array
    {
        return [
            'date'  => $this->valued_at->toDateString(),
            'value' => (float) $this->value,
        ];
    }
}
