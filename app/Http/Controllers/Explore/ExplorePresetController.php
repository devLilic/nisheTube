<?php

namespace App\Http\Controllers\Explore;

use App\Domain\Explore\Actions\CreateExplorePreset;
use App\Http\Controllers\Controller;
use App\Http\Requests\Explore\StoreExplorePresetRequest;
use App\Models\ExplorePreset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class ExplorePresetController extends Controller
{
    public function store(StoreExplorePresetRequest $request, CreateExplorePreset $create): RedirectResponse
    {
        Gate::authorize('create', ExplorePreset::class);
        $create->handle($request->user(), (string) $request->validated('name'), $request->filters());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Explore preset saved.')]);

        return back();
    }

    public function destroy(Request $request, ExplorePreset $explorePreset): RedirectResponse
    {
        Gate::authorize('delete', $explorePreset);
        $explorePreset->delete();
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Explore preset deleted.')]);

        return back();
    }
}
