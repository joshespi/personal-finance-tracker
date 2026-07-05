<?php

namespace App\Policies;

use App\Models\Pension;
use App\Models\User;

class PensionPolicy
{
    public function view(User $user, Pension $pension): bool
    {
        return $user->id === $pension->user_id;
    }

    public function update(User $user, Pension $pension): bool
    {
        return $user->id === $pension->user_id;
    }

    public function delete(User $user, Pension $pension): bool
    {
        return $user->id === $pension->user_id;
    }
}
