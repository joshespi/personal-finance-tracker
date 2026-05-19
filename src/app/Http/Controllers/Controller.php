<?php

namespace App\Http\Controllers;

use Carbon\Carbon;

abstract class Controller
{
    use \Illuminate\Foundation\Auth\Access\AuthorizesRequests;

    protected function monthNav(Carbon $month): array
    {
        return [
            'prevMonth'      => $month->copy()->subMonth()->format('Y-m'),
            'nextMonth'      => $month->copy()->addMonth()->format('Y-m'),
            'isCurrentMonth' => $month->isSameMonth(now()),
        ];
    }
}
