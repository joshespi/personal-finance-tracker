<?php

namespace App\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

trait AuthorizesOwnerView
{
    public function view(User $user, Model $model): bool
    {
        return $user->id === $this->ownerId($model);
    }

    abstract protected function ownerId(Model $model): int;
}
