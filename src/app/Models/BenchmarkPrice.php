<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BenchmarkPrice extends Model
{
    protected $fillable = ['ticker', 'recorded_on', 'close_price'];

    protected $casts = [
        'recorded_on' => 'date',
        'close_price' => 'decimal:8',
    ];
}
