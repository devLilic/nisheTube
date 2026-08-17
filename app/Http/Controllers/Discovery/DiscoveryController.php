<?php

namespace App\Http\Controllers\Discovery;

use App\Domain\Discovery\Actions\StartDiscoveryRun;
use App\Domain\Research\Enums\ResearchRunStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Discovery\StoreDiscoveryRunRequest;
use App\Http\ViewModels\DiscoveryRunViewModel;
use App\Models\DiscoveryRun;
use App\Models\Market;
use App\Models\ResearchRun;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class DiscoveryController extends Controller
{
    public function index(Request $request, DiscoveryRunViewModel $viewModel): Response
    {
        Gate::authorize('viewAny', DiscoveryRun::class);
        $user = $request->user();
        $sourceRunId = $request->string('source_run')->toString();

        return Inertia::render('discovery/index', [
            'markets' => Market::query()
                ->where('is_enabled', true)
                ->orderBy('sort_order')
                ->get(['key', 'name', 'region_code', 'relevance_language']),
            'default_market_key' => $user->default_market_key
                ?? Market::query()->where('is_enabled', true)->orderBy('sort_order')->value('key'),
            'submission_token' => (string) Str::uuid(),
            'sample_runs' => $user->researchRuns()
                ->where('status', ResearchRunStatus::Completed)
                ->whereHas('videoSnapshots')
                ->withCount('videoSnapshots')
                ->orderByRaw('CASE WHEN public_id = ? THEN 0 ELSE 1 END', [$sourceRunId])
                ->latest('completed_at')
                ->limit(50)
                ->get()
                ->map(fn (ResearchRun $run): array => [
                    'public_id' => $run->public_id,
                    'query_text' => $run->query_text,
                    'market_key' => $run->market_key,
                    'video_count' => $run->video_snapshots_count,
                    'completed_at' => $run->completed_at?->toIso8601String(),
                ]),
            'recent_runs' => $user->discoveryRuns()
                ->with('market')
                ->latest()
                ->limit(8)
                ->get()
                ->map(fn (DiscoveryRun $run): array => $viewModel->toArray($run, false)),
        ]);
    }

    public function store(StoreDiscoveryRunRequest $request, StartDiscoveryRun $startRun): RedirectResponse
    {
        Gate::authorize('create', DiscoveryRun::class);

        if ($request->submissionToken() !== null) {
            $existing = DiscoveryRun::query()
                ->where('user_id', $request->user()->id)
                ->where('submission_token', $request->submissionToken())
                ->first();

            if ($existing !== null) {
                return to_route('discovery.runs.show', $existing);
            }
        }
        $market = Market::query()->where('key', $request->marketKey())->firstOrFail();
        $seeds = [];

        foreach ($request->seeds() as $seed) {
            $sampleRun = ResearchRun::query()
                ->where('user_id', $request->user()->id)
                ->where('public_id', $seed['research_run_id'])
                ->where('status', ResearchRunStatus::Completed)
                ->where('market_key', $request->marketKey())
                ->whereHas('videoSnapshots')
                ->firstOrFail();
            $seeds[] = ['query' => $seed['query'], 'research_run' => $sampleRun];
        }

        $run = $startRun->handle(
            user: $request->user(),
            market: $market,
            seeds: $seeds,
            samplePerSeed: $request->samplePerSeed(),
            candidateLimit: $request->candidateLimit(),
            submissionToken: $request->submissionToken(),
            intakeContext: $request->intakeContext(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Discovery run queued.')]);

        return to_route('discovery.runs.show', $run);
    }
}
