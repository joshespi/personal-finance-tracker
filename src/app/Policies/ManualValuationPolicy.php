<?php

namespace App\Policies;

use App\Models\ManualValuation;
use App\Models\User;

class ManualValuationPolicy
{
    public function delete(User $user, ManualValuation $manualValuation): bool
    {
        return $user->id === $manualValuation->manualAsset->portfolio->user_id;
    }
}
