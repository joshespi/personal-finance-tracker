<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnvelopeTransaction extends Model
{
    protected $fillable = ['envelope_id', 'type', 'amount', 'description', 'occurred_at'];

    protected $casts = [
        'occurred_at' => 'date',
        'amount'      => 'decimal:8',
    ];

    public function envelope(): BelongsTo
    {
        return $this->belongsTo(Envelope::class);
    }
}
