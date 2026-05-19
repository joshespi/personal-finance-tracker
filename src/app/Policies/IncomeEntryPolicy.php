<?php

namespace App\Policies;

use App\Models\IncomeEntry;
use App\Models\User;

class IncomeEntryPolicy
{
    public function delete(User $user, IncomeEntry $incomeEntry): bool
    {
        return $user->id === $incomeEntry->user_id;
    }
}
