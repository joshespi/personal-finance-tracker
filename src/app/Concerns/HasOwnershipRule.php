<?php

namespace App\Concerns;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Shared `<model>_id belongs to this user` validation rule for owned models.
 * Derives the table from the model itself so each model doesn't hand-roll the
 * same `Rule::exists(...)->where('user_id', ...)` boilerplate.
 */
trait HasOwnershipRule
{
    /** Validation rule asserting an id in this model's table belongs to the given user. */
    public static function ownershipRule(int $userId): Exists
    {
        return Rule::exists((new static)->getTable(), 'id')->where('user_id', $userId);
    }
}
