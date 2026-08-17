<?php

namespace App\Http\Controllers\Research;

use App\Domain\Research\Actions\StartResearchRun;
use App\Domain\Research\Services\ResearchIntakeCatalog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Research\StoreResearchRunRequest;
use App\Http\ViewModels\ResearchRunViewModel;
use App\Models\Market;
use App\Models\ResearchRun;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ResearchController extends Controller
{
    public function create(Request $request, ResearchRunViewModel $viewModel, ResearchIntakeCatalog $intakeCatalog): Response
    {
        Gate::authorize('viewAny', ResearchRun::class);

        $user = $request->user();
        $repeat = $user->researchRuns()
            ->where('public_id', $request->string('repeat')->toString())
            ->first();

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
            'validation_presets' => $intakeCatalog->validationPresets(),
            'preflight' => $intakeCatalog->preflightConfiguration(),
            'submission_token' => (string) Str::uuid(),
            'repeat_source' => $repeat === null ? null : [
                'query_text' => $repeat->query_text,
                'market_key' => $repeat->market_key,
                'requested_result_count' => $repeat->requested_result_count,
                'published_window' => $repeat->parameters['published_window'] ?? 'custom',
                'published_after' => $repeat->parameters['published_after'] ?? '',
                'published_before' => $repeat->parameters['published_before'] ?? '',
                'search_order' => $repeat->parameters['search_order'] ?? 'relevance',
                'video_duration' => $repeat->parameters['video_duration'] ?? 'any',
                'video_category_id' => $repeat->parameters['video_category_id'] ?? '',
                'content_format' => $repeat->parameters['content_format'] ?? 'any',
                'target_channel_size' => $repeat->parameters['target_channel_size'] ?? 'any',
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

        if ($request->submissionToken() !== null) {
            $existing = ResearchRun::query()
                ->where('user_id', $request->user()->id)
                ->where('submission_token', $request->submissionToken())
                ->first();

            if ($existing !== null) {
                return to_route('research.runs.show', $existing);
            }
        }

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
            submissionToken: $request->submissionToken(),
            intakeContext: $request->intakeContext(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Research run queued.')]);

        return to_route('research.runs.show', $run);
    }
}
