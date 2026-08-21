<?php

namespace App\Http\Controllers\Watchlist;

use App\Domain\Watchlist\Actions\CreateWatchlistItem;
use App\Domain\Watchlist\Actions\StartWatchlistRefresh;
use App\Domain\Watchlist\Actions\UpdateWatchlistItem;
use App\Domain\Watchlist\Enums\WatchlistStatus;
use App\Domain\Watchlist\ReadModels\BuildWatchlistIndex;
use App\Http\Controllers\Controller;
use App\Http\Requests\Watchlist\RefreshWatchlistItemRequest;
use App\Http\Requests\Watchlist\StoreWatchlistItemRequest;
use App\Http\Requests\Watchlist\UpdateWatchlistItemRequest;
use App\Models\ResearchProject;
use App\Models\TopicWorkspace;
use App\Models\WatchlistItem;
use App\Models\WatchlistRefreshRun;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class WatchlistController extends Controller
{
    public function index(Request $request, BuildWatchlistIndex $view): Response
    {
        Gate::authorize('viewAny', WatchlistItem::class);
        $filters = [
            'search' => trim($request->string('search')->toString()),
            'type' => in_array($request->string('type')->toString(), ['video', 'channel'], true) ? $request->string('type')->toString() : 'all',
            'status' => in_array($request->string('status')->toString(), array_column(WatchlistStatus::cases(), 'value'), true) ? $request->string('status')->toString() : 'all',
            'activity' => in_array($request->string('activity')->toString(), ['active', 'paused'], true) ? $request->string('activity')->toString() : 'all',
            'project' => $request->string('project')->toString() ?: 'all',
        ];

        return Inertia::render('watchlist/index', $view->handle($request->user(), $filters));
    }

    public function store(StoreWatchlistItemRequest $request, CreateWatchlistItem $create): RedirectResponse
    {
        Gate::authorize('create', WatchlistItem::class);
        $project = $this->project($request->user()->id, $request->validated('project'));
        $item = $create->handle($request->user(), $request->targetType(), (string) $request->validated('target_reference'), $project, $request->note());
        Inertia::flash('toast', ['type' => 'success', 'message' => $item->wasRecentlyCreated ? __('Added to Watchlist.') : __('Already on your Watchlist.')]);

        return back();
    }

    public function update(UpdateWatchlistItemRequest $request, WatchlistItem $watchlistItem, UpdateWatchlistItem $update): RedirectResponse
    {
        Gate::authorize('update', $watchlistItem);
        $update->handle(
            $request->user(),
            $watchlistItem,
            WatchlistStatus::from((string) $request->validated('status')),
            (bool) $request->validated('is_active'),
            $this->project($request->user()->id, $request->validated('project')),
            $request->note(),
            $this->workspace($request->user()->id, $request->validated('workspace')),
            $request->has('notify_on_refresh') ? $request->boolean('notify_on_refresh') : $watchlistItem->notify_on_refresh,
        );
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Watchlist item updated.')]);

        return back();
    }

    public function refresh(RefreshWatchlistItemRequest $request, WatchlistItem $watchlistItem, StartWatchlistRefresh $start): RedirectResponse
    {
        Gate::authorize('refresh', $watchlistItem);
        $refresh = $start->handle($request->user(), $watchlistItem, $request->forceRefresh());
        Inertia::flash('toast', ['type' => 'success', 'message' => $refresh->wasRecentlyCreated ? __('Observation refresh queued.') : __('This item already has a refresh in progress.')]);

        return back();
    }

    public function retry(Request $request, WatchlistRefreshRun $watchlistRefreshRun, StartWatchlistRefresh $start): RedirectResponse
    {
        $item = WatchlistItem::query()->whereKey($watchlistRefreshRun->watchlist_item_id)->firstOrFail();
        Gate::authorize('refresh', $item);
        abort_unless($watchlistRefreshRun->user_id === $request->user()->id && $watchlistRefreshRun->status->isTerminal(), 404);
        $start->handle($request->user(), $item, true);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Watchlist refresh retry queued.')]);

        return back();
    }

    public function destroy(Request $request, WatchlistItem $watchlistItem): RedirectResponse
    {
        Gate::authorize('delete', $watchlistItem);
        $watchlistItem->update(['last_refresh_run_id' => null]);
        $watchlistItem->delete();
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Removed from Watchlist. Historical Analyzer observations remain subject to retention.')]);

        return back();
    }

    private function project(int $userId, mixed $publicId): ?ResearchProject
    {
        if (! is_string($publicId) || $publicId === '') {
            return null;
        }

        return ResearchProject::query()->where('user_id', $userId)->whereNull('archived_at')->where('public_id', $publicId)->firstOrFail();
    }

    private function workspace(int $userId, mixed $publicId): ?TopicWorkspace
    {
        if (! is_string($publicId) || $publicId === '') {
            return null;
        }

        return TopicWorkspace::query()->where('user_id', $userId)->whereNull('archived_at')->where('public_id', $publicId)->firstOrFail();
    }
}
