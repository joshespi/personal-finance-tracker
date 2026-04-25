<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'action', 'target_type', 'target_id', 'metadata', 'ip_address', 'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'metadata'   => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function record(string $action, ?Model $target = null, array $metadata = []): void
    {
        static::create([
            'user_id'     => auth()->id(),
            'action'      => $action,
            'target_type' => $target ? class_basename($target) : null,
            'target_id'   => $target?->getKey(),
            'metadata'    => $metadata ?: null,
            'ip_address'  => request()->ip(),
            'user_agent'  => substr(request()->userAgent() ?? '', 0, 255),
        ]);
    }

    public function actionLabel(): string
    {
        return match ($this->action) {
            'portfolio.created'   => 'Created portfolio',
            'portfolio.updated'   => 'Updated portfolio',
            'portfolio.deleted'   => 'Deleted portfolio',
            'transaction.created' => 'Added transaction',
            'transaction.deleted' => 'Deleted transaction',
            'watchlist.added'     => 'Added to watchlist',
            'watchlist.removed'   => 'Removed from watchlist',
            'impersonate.start'   => 'Started impersonation',
            'impersonate.stop'    => 'Stopped impersonation',
            'login'               => 'Logged in',
            default               => $this->action,
        };
    }
}
