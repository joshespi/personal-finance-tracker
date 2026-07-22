<?php

namespace App\Policies;

use App\Concerns\AuthorizesOwner;
use Illuminate\Database\Eloquent\Model;

class ManualAssetPolicy
{
    use AuthorizesOwner;

    protected function ownerId(Model $model): int
    {
        return $model->portfolio->user_id;
    }
}
