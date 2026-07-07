<?php

namespace App\Enums;

enum PriceFetchOutcome: string
{
    case Fetched     = 'fetched';
    case NoData      = 'no_data';
    case RateLimited = 'rate_limited';
}
