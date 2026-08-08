<?php

namespace App\Http\Controllers\Research;

use App\Domain\Research\Actions\StartResearchRun;
use App\Http\Controllers\Controller;
use App\Http\Requests\Research\StoreResearchRunRequest;
use App\Http\ViewModels\ResearchRunViewModel;
use App\Models\Market;
use App\Models\ResearchRun;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ResearchController extends Controller
{
    public function create(Request $request, ResearchRunViewModel $viewModel): Response
    {
        Gate::authorize('viewAny', ResearchRun::class);

        $user = $request->user();

        return Inertia::render('research/create', [
            'markets' => Market::query()
                ->where('is_enabled', true)
                ->orderBy('sort_order')
                ->get(['key', 'name', 'region_code', 'relevance_language']),
            'defaults' => [
                'market_key' => $user->default_market_key
                    ?? Market::query()->where('is_enabled', true)->orderBy('sort_order')->value('key'),
                'result_depth' => $user->default_result_depth,
            ],
            'recent_runs' => $user->researchRuns()
                ->latest()
                ->limit(5)
                ->get()
                ->map(fn (ResearchRun $run): array => $viewModel->toArray($run, false)),
        ]);
    }

    public function store(
        StoreResearchRunRequest $request,
        StartResearchRun $startResearchRun,
    ): RedirectResponse {
        Gate::authorize('create', ResearchRun::class);

        $market = Market::query()->where('key', $request->marketKey())->firstOrFail();
        $run = $startResearchRun->handle(
            user: $request->user(),
            market: $market,
            queryText: $request->queryText(),
            requestedResultCount: $request->requestedResultCount(),
            searchOrder: $request->searchOrder(),
            publishedWindow: $request->publishedWindow(),
            publishedAfter: $request->publishedAfter(),
            publishedBefore: $request->publishedBefore(),
            videoDuration: $request->videoDuration(),
            videoCategoryId: $request->videoCategoryId(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Research run queued.')]);

        return to_route('research.runs.show', $run);
    }
}
