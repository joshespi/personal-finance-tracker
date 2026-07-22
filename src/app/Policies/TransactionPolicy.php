<?php

namespace App\Policies;

use App\Concerns\AuthorizesOwnerDelete;
use App\Concerns\AuthorizesOwnerUpdate;
use Illuminate\Database\Eloquent\Model;

class TransactionPolicy
{
    use AuthorizesOwnerDelete, AuthorizesOwnerUpdate;

    protected function ownerId(Model $model): int
    {
        return $model->portfolio->user_id;
    }
}
