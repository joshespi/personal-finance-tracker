<?php

namespace App\Policies;

use App\Concerns\AuthorizesOwnerDelete;
use Illuminate\Database\Eloquent\Model;

class PortfolioSlicePolicy
{
    use AuthorizesOwnerDelete;

    protected function ownerId(Model $model): int
    {
        return $model->portfolio->user_id;
    }
}
