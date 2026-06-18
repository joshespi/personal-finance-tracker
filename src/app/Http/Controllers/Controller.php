<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    use AuthorizesRequests;

    protected function monthNav(Carbon $month): array
    {
        return [
            'prevMonth'      => $month->copy()->subMonth()->format('Y-m'),
            'nextMonth'      => $month->copy()->addMonth()->format('Y-m'),
            'isCurrentMonth' => $month->isSameMonth(now()),
        ];
    }
}
