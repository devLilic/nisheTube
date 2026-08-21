<?php

namespace App\Http\Controllers\Research;

use App\Domain\Analyzer\ValueObjects\AnalyzerNavigationContext;
use App\Domain\Research\Actions\CancelQueuedResearchRun;
use App\Domain\Research\Actions\RetryResearchRun;
use App\Http\Controllers\Controller;
use App\Http\Requests\Research\ResearchEvidenceRequest;
use App\Http\ViewModels\LibraryViewModel;
use App\Http\ViewModels\ResearchRunViewModel;
use App\Models\ResearchRun;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ResearchRunController extends Controller
{
    public function show(ResearchEvidenceRequest $request, ResearchRun $researchRun, ResearchRunViewModel $viewModel, LibraryViewModel $libraryViewModel): Response
    {
        Gate::authorize('view', $researchRun);

        return Inertia::render('research/show', [
            'run' => $viewModel->toArray($researchRun, evidenceQuery: $request->evidenceQuery()),
            'library' => $libraryViewModel->context($request->user()),
            'workspaces' => $request->user()->topicWorkspaces()->whereNull('archived_at')->orderBy('name')->get(['public_id', 'name', 'market_key']),
            'returnTo' => AnalyzerNavigationContext::fromInput($request->query('return_to'))->returnUrl,
        ]);
    }

    public function retry(
        Request $request,
        ResearchRun $researchRun,
        RetryResearchRun $retryResearchRun,
    ): RedirectResponse {
        Gate::authorize('retry', $researchRun);

        $retry = $retryResearchRun->handle($request->user(), $researchRun);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Research run queued again.')]);

        return to_route('research.runs.show', $retry);
    }

    public function cancel(
        Request $request,
        ResearchRun $researchRun,
        CancelQueuedResearchRun $cancelQueuedResearchRun,
    ): RedirectResponse {
        Gate::authorize('cancel', $researchRun);

        try {
            $cancelQueuedResearchRun->handle($request->user(), $researchRun);
        } catch (DomainException) {
            Inertia::flash('toast', ['type' => 'warning', 'message' => __('This research run has already started and can no longer be cancelled.')]);

            return to_route('research.runs.show', $researchRun);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Queued research run cancelled before collection started.')]);

        return to_route('research.runs.show', $researchRun);
    }
}
