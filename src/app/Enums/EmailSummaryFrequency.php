<?php

namespace App\Enums;

use Carbon\CarbonInterface;

/**
 * Cadences a user can opt into for the periodic account-summary email. Multiple
 * cadences can be selected at once (e.g. both Weekly and Monthly), each firing
 * on its own fixed calendar rule — see EmailSummaryFrequency::isDueToday().
 */
enum EmailSummaryFrequency: string
{
    case Daily   = 'daily';
    case Weekly  = 'weekly';
    case Monthly = 'monthly';

    public function label(): string
    {
        return match ($this) {
            self::Daily   => 'Daily',
            self::Weekly  => 'Weekly',
            self::Monthly => 'Monthly',
        };
    }

    /** Fixed calendar rule per cadence: daily always fires, weekly on Mondays, monthly on the 1st. */
    public function isDueToday(): bool
    {
        return match ($this) {
            self::Daily   => true,
            self::Weekly  => now()->isMonday(),
            self::Monthly => now()->day === 1,
        };
    }

    /** The comparison window's start for this cadence — one period back from $until. */
    public function periodStart(CarbonInterface $until): CarbonInterface
    {
        return match ($this) {
            self::Daily   => $until->copy()->subDay(),
            self::Weekly  => $until->copy()->subWeek(),
            self::Monthly => $until->copy()->subMonth(),
        };
    }

    /**
     * Whether this cadence's window is always fully covered by $other's whenever both are
     * due the same day, making this one redundant for a user opted into both. Currently the
     * only such pair: Daily is subsumed by Weekly, since "today" is always inside the past-week
     * range. Monthly isn't subsumed by anything here — its overlap with Daily isn't
     * calendar-guaranteed the same way, and the two summaries cover genuinely different-length
     * windows.
     */
    public function isSubsumedBy(self $other): bool
    {
        return $this === self::Daily && $other === self::Weekly;
    }

    /** Flat array of string values — for Rule::in / validation. */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
