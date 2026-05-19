<?php

namespace App\Policies;

use App\Models\CashAccount;
use App\Models\User;

class CashAccountPolicy
{
    public function view(User $user, CashAccount $cashAccount): bool
    {
        return $user->id === $cashAccount->user_id;
    }

    public function update(User $user, CashAccount $cashAccount): bool
    {
        return $user->id === $cashAccount->user_id;
    }

    public function delete(User $user, CashAccount $cashAccount): bool
    {
        return $user->id === $cashAccount->user_id;
    }
}
