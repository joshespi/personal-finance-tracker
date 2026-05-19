<?php

namespace App\Policies;

use App\Models\ManualAsset;
use App\Models\User;

class ManualAssetPolicy
{
    public function view(User $user, ManualAsset $manualAsset): bool
    {
        return $user->id === $manualAsset->portfolio->user_id;
    }

    public function update(User $user, ManualAsset $manualAsset): bool
    {
        return $user->id === $manualAsset->portfolio->user_id;
    }

    public function delete(User $user, ManualAsset $manualAsset): bool
    {
        return $user->id === $manualAsset->portfolio->user_id;
    }
}
