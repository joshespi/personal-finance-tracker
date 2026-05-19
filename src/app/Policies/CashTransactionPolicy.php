<?php

namespace App\Policies;

use App\Models\CashTransaction;
use App\Models\User;

class CashTransactionPolicy
{
    public function delete(User $user, CashTransaction $cashTransaction): bool
    {
        return $user->id === $cashTransaction->cashAccount->user_id;
    }
}
