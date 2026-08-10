<?php

namespace App\Http\Controllers\Analyzer;

use App\Domain\Analyzer\Actions\StartAnalyzerRun;
use App\Domain\Collection\Enums\CollectionCachePolicy;
use App\Http\Controllers\Controller;
use App\Http\Requests\Analyzer\StoreAnalyzerRunRequest;
use App\Http\ViewModels\AnalyzerRunViewModel;
use App\Models\AnalyzerRun;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AnalyzerController extends Controller
{
    public function index(Request $request, AnalyzerRunViewModel $viewModel): Response
    {
        Gate::authorize('viewAny', AnalyzerRun::class);

        return Inertia::render('analyzer/index', [
            'prefill' => [
                'target_kind' => $request->query('channel') ? 'channel' : 'video',
                'target_reference' => (string) ($request->query('channel') ?: $request->query('video', '')),
                'origin_kind' => $request->query('origin') === 'search' ? 'search' : 'manual',
                'origin_reference' => $request->query('origin_reference'),
                'return_to' => $request->query('return_to'),
            ],
            'recent_video_runs' => $this->recentRuns($viewModel, $request->user()->analyzerRuns()
                ->where('target_kind', 'video')
                ->orderByDesc('created_at')
                ->orderByDesc('id'), 'video_page'),
            'recent_channel_runs' => $this->recentRuns($viewModel, $request->user()->analyzerRuns()
                ->where('target_kind', 'channel')
                ->orderByDesc('created_at')
                ->orderByDesc('id'), 'channel_page'),
        ]);
    }

    /** @param  Builder<AnalyzerRun>|HasMany<AnalyzerRun, User>  $query
     * @return array{data: array<int, array<string, mixed>>, current_page: int, last_page: int, from: int|null, to: int|null, total: int, per_page: int}
     */
    private function recentRuns(AnalyzerRunViewModel $viewModel, Builder|HasMany $query, string $pageName): array
    {
        /** @var LengthAwarePaginator<int, AnalyzerRun> $paginator */
        $paginator = $query->paginate(10, ['*'], $pageName);

        return [
            'data' => $viewModel->summaries(collect($paginator->items())),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
        ];
    }

    public function store(StoreAnalyzerRunRequest $request, StartAnalyzerRun $startAnalyzerRun): RedirectResponse
    {
        Gate::authorize('create', AnalyzerRun::class);
        $run = $startAnalyzerRun->handleTarget(
            $request->user(),
            $request->targetKind(),
            $request->targetReference(),
            CollectionCachePolicy::AllowFreshCache,
            $request->originKind(),
            $request->originReference(),
            $request->returnTo(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Analyzer run queued.')]);

        return to_route('analyzer.runs.show', $run);
    }
}
