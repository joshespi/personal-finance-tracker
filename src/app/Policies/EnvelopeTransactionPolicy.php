<?php

namespace App\Policies;

use App\Models\EnvelopeTransaction;
use App\Models\User;

class EnvelopeTransactionPolicy
{
    public function delete(User $user, EnvelopeTransaction $envelopeTransaction): bool
    {
        return $user->id === $envelopeTransaction->envelope->user_id;
    }
}
