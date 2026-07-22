<?php

namespace App\Policies;

use App\Concerns\AuthorizesOwnerDelete;
use Illuminate\Database\Eloquent\Model;

class EnvelopeTransactionPolicy
{
    use AuthorizesOwnerDelete;

    protected function ownerId(Model $model): int
    {
        return $model->envelope->user_id;
    }
}
