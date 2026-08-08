<?php

namespace App\Http\Controllers\Library;

use App\Domain\Library\Actions\CreateFavorite;
use App\Domain\Library\Actions\DeleteFavorite;
use App\Domain\Library\Actions\UpdateFavorite;
use App\Domain\Library\Services\ResolveLibraryTarget;
use App\Http\Controllers\Controller;
use App\Http\Requests\Library\StoreFavoriteRequest;
use App\Http\Requests\Library\UpdateFavoriteRequest;
use App\Http\ViewModels\LibraryViewModel;
use App\Models\Favorite;
use App\Models\ResearchProject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class FavoriteController extends Controller
{
    public function index(Request $request, LibraryViewModel $viewModel): Response
    {
        Gate::authorize('viewAny', Favorite::class);

        return Inertia::render('library/favorites/index', $viewModel->favoritesIndex($request->user(), $request));
    }

    public function store(
        StoreFavoriteRequest $request,
        ResolveLibraryTarget $resolveTarget,
        CreateFavorite $createFavorite,
    ): RedirectResponse {
        Gate::authorize('create', Favorite::class);
        $target = $resolveTarget->handle(
            $request->user(),
            $request->targetType(),
            (string) $request->validated('target_reference'),
        );
        $createFavorite->handle(
            $request->user(),
            $target,
            $this->project($request),
            $this->optionalString($request->validated('note')),
        );
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Saved to favorites.')]);

        return back();
    }

    public function update(
        UpdateFavoriteRequest $request,
        Favorite $favorite,
        UpdateFavorite $updateFavorite,
    ): RedirectResponse {
        Gate::authorize('update', $favorite);
        $updateFavorite->handle(
            $request->user(),
            $favorite,
            $this->project($request),
            $this->optionalString($request->validated('note')),
        );
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Favorite updated.')]);

        return back();
    }

    public function destroy(Request $request, Favorite $favorite, DeleteFavorite $deleteFavorite): RedirectResponse
    {
        Gate::authorize('delete', $favorite);
        $deleteFavorite->handle($request->user(), $favorite);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Favorite removed.')]);

        return back();
    }

    private function project(Request $request): ?ResearchProject
    {
        $publicId = $request->input('project_public_id');

        if (! is_string($publicId) || $publicId === '') {
            return null;
        }

        return ResearchProject::query()
            ->where('user_id', $request->user()->id)
            ->where('public_id', $publicId)
            ->firstOrFail();
    }

    private function optionalString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
