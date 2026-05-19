<?php

namespace App\Policies;

use App\Models\PortfolioSlice;
use App\Models\User;

class PortfolioSlicePolicy
{
    public function delete(User $user, PortfolioSlice $portfolioSlice): bool
    {
        return $user->id === $portfolioSlice->portfolio->user_id;
    }
}
