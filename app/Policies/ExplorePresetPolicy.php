<?php

namespace App\Policies;

use App\Models\ExplorePreset;
use App\Models\User;

class ExplorePresetPolicy
{
    public function create(User $user): bool
    {
        return true;
    }

    public function delete(User $user, ExplorePreset $preset): bool
    {
        return $preset->user_id === $user->id;
    }
}
