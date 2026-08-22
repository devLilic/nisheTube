<?php

namespace App\Http\Controllers\Localization;

use App\Domain\Localization\Actions\UpdateUiLocale;
use App\Http\Controllers\Controller;
use App\Http\Requests\Localization\UiLocaleUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final class UiLocaleController extends Controller
{
    public function update(
        UiLocaleUpdateRequest $request,
        UpdateUiLocale $updateUiLocale,
    ): RedirectResponse {
        $user = $request->user();

        if ($user !== null) {
            Gate::authorize('update', $user);
        }

        $locale = $request->uiLocale();
        $updateUiLocale->handle($user, $request->session(), $locale);
        App::setLocale($locale->value);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Interface language updated.')]);

        return back();
    }
}
