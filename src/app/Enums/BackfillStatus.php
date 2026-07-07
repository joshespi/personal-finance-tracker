<?php

namespace App\Enums;

enum BackfillStatus: string
{
    case Pending    = 'pending';
    case InProgress = 'in_progress';
    case Completed  = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Pending    => 'Pending',
            self::InProgress => 'In progress',
            self::Completed  => 'Completed',
        };
    }
}
