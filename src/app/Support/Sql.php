<?php

namespace App\Support;

class Sql
{
    /**
     * Escape LIKE wildcards in user input so it matches literally — pair with an
     * explicit `LIKE ? ESCAPE '!'` clause (see self::LIKE_ESCAPE).
     *
     * '!' as the escape character rather than a backslash: MySQL/MariaDB default to
     * backslash but SQLite (which the test suite runs on) has no default at all, so the
     * clause has to be explicit either way — and a non-backslash escape avoids a second
     * round of backslash-doubling between PHP, the driver, and SQL.
     */
    public static function escapeLike(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    }

    /** The ESCAPE clause that self::escapeLike() escapes for. */
    public const LIKE_ESCAPE = "ESCAPE '!'";
}
