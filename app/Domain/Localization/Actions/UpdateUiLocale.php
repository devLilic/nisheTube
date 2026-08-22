<?php

namespace App\Domain\Localization\Actions;

use App\Domain\Localization\Enums\UiLocale;
use App\Domain\Localization\Services\ResolveUiLocale;
use App\Models\User;
use Illuminate\Contracts\Session\Session;

final class UpdateUiLocale
{
    public function handle(?User $user, Session $session, UiLocale $locale): void
    {
        if ($user !== null && $user->ui_locale !== $locale) {
            $user->update(['ui_locale' => $locale]);
        }

        $session->put(ResolveUiLocale::SESSION_KEY, $locale->value);
    }
}
