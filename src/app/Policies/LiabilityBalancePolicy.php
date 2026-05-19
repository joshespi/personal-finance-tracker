<?php

namespace App\Policies;

use App\Models\LiabilityBalance;
use App\Models\User;

class LiabilityBalancePolicy
{
    public function delete(User $user, LiabilityBalance $liabilityBalance): bool
    {
        return $user->id === $liabilityBalance->liability->user_id;
    }
}
