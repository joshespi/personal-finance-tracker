<?php

namespace App\Policies;

use App\Concerns\AuthorizesOwnerDelete;
use App\Concerns\AuthorizesOwnerUpdate;
use Illuminate\Database\Eloquent\Model;

class CashTransactionPolicy
{
    use AuthorizesOwnerDelete, AuthorizesOwnerUpdate;

    protected function ownerId(Model $model): int
    {
        return $model->cashAccount->user_id;
    }
}
