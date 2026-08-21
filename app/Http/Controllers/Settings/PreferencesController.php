<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Settings\Actions\UpdateUserPreferences;
use App\Domain\Settings\ReadModels\BuildPreferencesPage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PreferencesUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PreferencesController extends Controller
{
    public function edit(BuildPreferencesPage $preferences): Response
    {
        return Inertia::render('settings/preferences', [
            'preferenceOptions' => $preferences->handle(),
        ]);
    }

    public function update(
        PreferencesUpdateRequest $request,
        UpdateUserPreferences $updateUserPreferences,
    ): RedirectResponse {
        $user = $request->user();

        Gate::authorize('update', $user);

        $updateUserPreferences->handle(
            $user,
            $request->timezone(),
            $request->defaultMarketKey(),
            $request->defaultResultDepth(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Preferences updated.')]);

        return to_route('preferences.edit');
    }
}
