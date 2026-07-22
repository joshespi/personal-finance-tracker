<?php

namespace App\Policies;

use App\Concerns\AuthorizesOwnerDelete;
use Illuminate\Database\Eloquent\Model;

class ManualValuationPolicy
{
    use AuthorizesOwnerDelete;

    protected function ownerId(Model $model): int
    {
        return $model->manualAsset->portfolio->user_id;
    }
}
