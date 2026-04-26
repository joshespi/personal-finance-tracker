<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiabilityBalance extends Model
{
    protected $fillable = ['liability_id', 'balance', 'notes', 'recorded_at'];

    protected $casts = [
        'recorded_at' => 'datetime',
        'balance'     => 'decimal:8',
    ];

    public function liability(): BelongsTo
    {
        return $this->belongsTo(Liability::class);
    }
}
