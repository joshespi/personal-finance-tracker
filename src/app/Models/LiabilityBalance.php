<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiabilityBalance extends Model
{
    use HasFactory;

    protected $fillable = ['liability_id', 'balance', 'notes', 'recorded_at'];

    protected $casts = [
        'recorded_at' => 'datetime',
        'balance'     => 'decimal:8',
    ];

    public function liability(): BelongsTo
    {
        return $this->belongsTo(Liability::class);
    }

    public function toBackupArray(): array
    {
        return [
            'date'    => $this->recorded_at->toDateString(),
            'balance' => (float) $this->balance,
        ];
    }
}
