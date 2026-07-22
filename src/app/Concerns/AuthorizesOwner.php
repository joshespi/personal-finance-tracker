<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Every Policy in this app is `$user->id === <ownership path>->user_id` for
 * view/update/delete, differing only in the relation path to the owning user —
 * this collapses that repeated one-liner into a single implementation. Each
 * policy declares just its ownership path via ownerId().
 *
 * Policies that don't need all three abilities compose the narrower
 * AuthorizesOwnerView/Update/Delete traits directly instead, so a policy never
 * grants an ability nothing in the app actually checks.
 */
trait AuthorizesOwner
{
    use AuthorizesOwnerDelete, AuthorizesOwnerUpdate, AuthorizesOwnerView;

    abstract protected function ownerId(Model $model): int;
}
