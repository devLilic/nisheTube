<?php

namespace App\Domain\Explore\Actions;

use App\Models\ExplorePreset;
use App\Models\User;

class CreateExplorePreset
{
    /** @param array<string, mixed> $filters */
    public function handle(User $user, string $name, array $filters): ExplorePreset
    {
        return ExplorePreset::query()->create([
            'user_id' => $user->id,
            'name' => trim($name),
            'filters' => $filters,
        ]);
    }
}
