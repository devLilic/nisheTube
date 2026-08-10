<?php

namespace App\Http\Controllers\Analyzer;

use App\Domain\Analyzer\Actions\StartAnalyzerRun;
use App\Domain\Analyzer\ValueObjects\AnalyzerNavigationContext;
use App\Domain\Collection\Enums\CollectionCachePolicy;
use App\Http\Controllers\Controller;
use App\Http\Requests\Analyzer\RefreshAnalyzerRunRequest;
use App\Http\ViewModels\AnalyzerRunViewModel;
use App\Http\ViewModels\LibraryViewModel;
use App\Models\AnalyzerRun;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AnalyzerRunController extends Controller
{
    public function show(
        Request $request,
        AnalyzerRun $analyzerRun,
        AnalyzerRunViewModel $viewModel,
        LibraryViewModel $libraryViewModel,
    ): Response {
        Gate::authorize('view', $analyzerRun);

        $payload = $viewModel->toArray($analyzerRun, commentPage: $request->integer('comments_page', 1));
        $requestedReturn = AnalyzerNavigationContext::fromInput($request->string('return_to')->toString())->returnUrl;
        if ($requestedReturn !== null) {
            $payload['origin']['return_url'] = $requestedReturn;
        }

        return Inertia::render('analyzer/show', [
            'run' => $payload,
            'library' => $libraryViewModel->context($analyzerRun->user),
            'workspaces' => $analyzerRun->user->topicWorkspaces()->whereNull('archived_at')->orderBy('name')->get(['public_id', 'name', 'market_key']),
        ]);
    }

    public function refresh(
        RefreshAnalyzerRunRequest $request,
        AnalyzerRun $analyzerRun,
        StartAnalyzerRun $startAnalyzerRun,
    ): RedirectResponse {
        Gate::authorize('refresh', $analyzerRun);
        $run = $startAnalyzerRun->handleTarget(
            $request->user(),
            $analyzerRun->target_kind,
            $analyzerRun->target_provider_id,
            $request->forceRefresh() ? CollectionCachePolicy::ForceRefresh : CollectionCachePolicy::AllowFreshCache,
            'refresh',
            $analyzerRun->public_id,
            $analyzerRun->navigation_context['return_url'] ?? null,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $request->forceRefresh() ? __('Force refresh queued.') : __('Analyzer refresh queued.'),
        ]);

        return to_route('analyzer.runs.show', $run);
    }
}
