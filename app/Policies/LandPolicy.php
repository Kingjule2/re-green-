<?php

namespace App\Policies;

use App\Models\Land;
use App\Models\User;

/**
 * Who may do what with a land.
 *
 * A farmer owns the lands they registered. Pemda/NGO and corporate accounts are
 * data consumers: they may read every land — that is the aggregate picture they
 * pay for — but they may not change a land that belongs to somebody else.
 */
class LandPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Land $land): bool
    {
        return $user->viewsAllLands() || $land->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->isFarmer();
    }

    public function update(User $user, Land $land): bool
    {
        return $user->isFarmer() && $land->user_id === $user->id;
    }

    public function delete(User $user, Land $land): bool
    {
        return $user->isFarmer() && $land->user_id === $user->id;
    }

    /**
     * Uploading a photo for a land, and deciding which crop to plant on it, is
     * the owner's call: the data only means something from the person standing
     * on the land.
     */
    public function manage(User $user, Land $land): bool
    {
        return $user->isFarmer() && $land->user_id === $user->id;
    }
}
