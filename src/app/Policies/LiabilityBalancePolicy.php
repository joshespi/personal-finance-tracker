<?php

namespace App\Policies;

use App\Concerns\AuthorizesOwnerDelete;
use Illuminate\Database\Eloquent\Model;

class LiabilityBalancePolicy
{
    use AuthorizesOwnerDelete;

    protected function ownerId(Model $model): int
    {
        return $model->liability->user_id;
    }
}
