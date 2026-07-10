<?php

namespace App\Models;

use App\Enums\BackfillStatus;
use Illuminate\Database\Eloquent\Model;

class BackfillRequest extends Model
{
    protected $fillable = [
        'portfolio_ids', 'from_date', 'to_date', 'status',
        'total_assets', 'pending_asset_ids', 'asset_ids', 'write_cursor', 'last_note', 'completed_at',
    ];

    protected $casts = [
        'portfolio_ids'     => 'array',
        'pending_asset_ids' => 'array',
        'asset_ids'         => 'array',
        'from_date'         => 'date',
        'to_date'           => 'date',
        'write_cursor'      => 'date',
        'completed_at'      => 'datetime',
    ];

    public function fetchedCount(): int
    {
        return $this->total_assets - count($this->pending_asset_ids);
    }

    public function totalDays(): int
    {
        // Guards against a negative/zero count if to_date < from_date were ever persisted
        // (diffInDays() is signed on Carbon 3.x, not clamped to an absolute value).
        return max(1, $this->from_date->diffInDays($this->to_date) + 1);
    }

    public function writtenDays(): int
    {
        if (! $this->write_cursor) {
            return 0;
        }

        return max(0, min($this->totalDays(), $this->from_date->diffInDays($this->write_cursor)));
    }

    /** Human-readable progress for whichever phase (fetching assets, then writing days) is currently active. */
    public function progressLabel(): string
    {
        if (! empty($this->pending_asset_ids)) {
            return "{$this->fetchedCount()}/{$this->total_assets} assets";
        }

        return "{$this->writtenDays()}/{$this->totalDays()} days";
    }

    public function statusLabel(): string
    {
        return BackfillStatus::from($this->status)->label();
    }
}
