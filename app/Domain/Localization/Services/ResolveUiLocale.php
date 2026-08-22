<?php

namespace App\Domain\Localization\Services;

use App\Domain\Localization\Enums\UiLocale;
use Illuminate\Http\Request;

final class ResolveUiLocale
{
    public const SESSION_KEY = 'ui_locale';

    public function forRequest(Request $request): UiLocale
    {
        $userLocale = $request->user()?->ui_locale;

        if ($userLocale instanceof UiLocale) {
            return $userLocale;
        }

        return $this->forGuestSession($request->session()->get(self::SESSION_KEY));
    }

    public function forGuestSession(mixed $locale): UiLocale
    {
        if (is_string($locale) && ($resolved = UiLocale::tryFrom($locale)) !== null) {
            return $resolved;
        }

        return UiLocale::Romanian;
    }
}
