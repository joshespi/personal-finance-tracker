<?php

namespace App\Policies;

use App\Models\IncomeCategory;
use App\Models\User;

class IncomeCategoryPolicy
{
    public function view(User $user, IncomeCategory $incomeCategory): bool
    {
        return $user->id === $incomeCategory->user_id;
    }

    public function update(User $user, IncomeCategory $incomeCategory): bool
    {
        return $user->id === $incomeCategory->user_id;
    }

    public function delete(User $user, IncomeCategory $incomeCategory): bool
    {
        return $user->id === $incomeCategory->user_id;
    }
}
