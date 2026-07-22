<?php

namespace App\Policies;

use App\Concerns\AuthorizesOwner;
use Illuminate\Database\Eloquent\Model;

class LiabilityPolicy
{
    use AuthorizesOwner;

    protected function ownerId(Model $model): int
    {
        return $model->user_id;
    }
}
