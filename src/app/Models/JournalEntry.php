<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use League\CommonMark\CommonMarkConverter;

class JournalEntry extends Model
{
    use HasFactory;

    protected $fillable = ['portfolio_id', 'title', 'body', 'entry_date'];

    protected $casts = [
        'entry_date' => 'date',
    ];

    public function portfolio(): BelongsTo
    {
        return $this->belongsTo(Portfolio::class);
    }

    /** Built once and shared: the index page renders every entry, and the converter is stateless. */
    private static ?CommonMarkConverter $converter = null;

    public function getRenderedBodyAttribute(): string
    {
        self::$converter ??= new CommonMarkConverter([
            'html_input'         => 'strip',
            'allow_unsafe_links' => false,
        ]);

        return self::$converter->convert($this->body ?? '')->getContent();
    }

    public function toBackupArray(): array
    {
        return [
            'date'  => $this->entry_date->toDateString(),
            'title' => $this->title,
            'body'  => $this->body,
        ];
    }
}
