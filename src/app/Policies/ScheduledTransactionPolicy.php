<?php

namespace App\Policies;

use App\Models\ScheduledTransaction;
use App\Models\User;

class ScheduledTransactionPolicy
{
    public function view(User $user, ScheduledTransaction $scheduledTransaction): bool
    {
        return $user->id === $scheduledTransaction->user_id;
    }

    public function update(User $user, ScheduledTransaction $scheduledTransaction): bool
    {
        return $user->id === $scheduledTransaction->user_id;
    }

    public function delete(User $user, ScheduledTransaction $scheduledTransaction): bool
    {
        return $user->id === $scheduledTransaction->user_id;
    }
}
