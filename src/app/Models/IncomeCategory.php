<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class IncomeCategory extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'name', 'color', 'sort_order'];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    /** Validation rule asserting an income_category_id belongs to the given user. */
    public static function ownershipRule(int $userId): Exists
    {
        return Rule::exists('income_categories', 'id')->where('user_id', $userId);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cashTransactions(): HasMany
    {
        return $this->hasMany(CashTransaction::class);
    }

    public function incomeEntries(): HasMany
    {
        return $this->hasMany(IncomeEntry::class);
    }
}
