<?php

namespace App\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

trait AuthorizesOwnerDelete
{
    public function delete(User $user, Model $model): bool
    {
        return $user->id === $this->ownerId($model);
    }

    abstract protected function ownerId(Model $model): int;
}
