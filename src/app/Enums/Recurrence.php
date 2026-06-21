<?php

namespace App\Enums;

use Carbon\Carbon;

enum Recurrence: string
{
    case Monthly   = 'monthly';
    case Weekly    = 'weekly';
    case Biweekly  = 'biweekly';
    case Quarterly = 'quarterly';
    case Yearly    = 'yearly';

    public function label(): string
    {
        return match ($this) {
            self::Monthly   => 'Monthly',
            self::Weekly    => 'Weekly',
            self::Biweekly  => 'Biweekly',
            self::Quarterly => 'Quarterly',
            self::Yearly    => 'Yearly',
        };
    }

    /** Longer label carrying the cadence hint — for the <select> on the schedule form. */
    public function formLabel(): string
    {
        return match ($this) {
            self::Biweekly  => 'Biweekly (every 2 weeks)',
            self::Quarterly => 'Quarterly (every 3 months)',
            default         => $this->label(),
        };
    }

    /** Advance a date by one period of this cadence. */
    public function advance(Carbon $date): Carbon
    {
        return match ($this) {
            self::Weekly    => $date->copy()->addWeek(),
            self::Biweekly  => $date->copy()->addWeeks(2),
            self::Quarterly => $date->copy()->addMonths(3),
            self::Yearly    => $date->copy()->addYear(),
            self::Monthly   => $date->copy()->addMonth(),
        };
    }

    /** Flat array of string values — for Rule::in / validation. */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
