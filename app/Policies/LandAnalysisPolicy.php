<?php

namespace App\Policies;

use App\Models\Land;
use App\Models\LandAnalysis;
use App\Models\User;

/**
 * Analyses follow the land they describe: whoever may read the land may read
 * its monitoring history, and only the land owner may add a photo or delete a
 * period.
 */
class LandAnalysisPolicy
{
    public function view(User $user, LandAnalysis $analysis): bool
    {
        $land = $analysis->land;

        return $land === null
            ? $analysis->user_id === $user->id || $user->viewsAllLands()
            : $user->can('view', $land);
    }

    public function create(User $user, Land $land): bool
    {
        return $user->can('manage', $land);
    }

    public function delete(User $user, LandAnalysis $analysis): bool
    {
        $land = $analysis->land;

        return $land !== null && $user->can('manage', $land);
    }
}
