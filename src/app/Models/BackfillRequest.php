<?php

namespace App\Models;

use App\Enums\BackfillStatus;
use Illuminate\Database\Eloquent\Model;

class BackfillRequest extends Model
{
    protected $fillable = [
        'portfolio_ids', 'from_date', 'to_date', 'status',
        'total_assets', 'pending_asset_ids', 'last_note', 'completed_at',
    ];

    protected $casts = [
        'portfolio_ids'     => 'array',
        'pending_asset_ids' => 'array',
        'from_date'         => 'date',
        'to_date'           => 'date',
        'completed_at'      => 'datetime',
    ];

    public function fetchedCount(): int
    {
        return $this->total_assets - count($this->pending_asset_ids);
    }

    public function portfolioIdsCsv(): string
    {
        return implode(',', $this->portfolio_ids);
    }

    public function statusLabel(): string
    {
        return BackfillStatus::from($this->status)->label();
    }
}
