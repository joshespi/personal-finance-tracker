<?php

namespace App\Enums\Concerns;

/** Shared backing-value helpers for string-backed enums. */
trait HasValues
{
    /** Flat array of string values — for Eloquent whereIn, Rule::in, validation. */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** Backing values of the cases passing $filter, reindexed. */
    protected static function subsetValues(callable $filter): array
    {
        return array_values(array_map(
            fn (self $t) => $t->value,
            array_filter(self::cases(), $filter)
        ));
    }
}
