<?php

namespace App\Domain\Settings\Actions;

use App\Models\User;

class UpdateUserPreferences
{
    public function handle(
        User $user,
        string $timezone,
        ?string $defaultMarketKey,
        ?int $defaultResultDepth = null,
    ): void {
        $attributes = [
            'timezone' => $timezone,
            'default_market_key' => $defaultMarketKey,
        ];

        if ($defaultResultDepth !== null) {
            $attributes['default_result_depth'] = $defaultResultDepth;
        }

        $user->update($attributes);
    }
}
