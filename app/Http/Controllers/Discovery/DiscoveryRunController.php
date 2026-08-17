<?php

namespace App\Http\Controllers\Discovery;

use App\Domain\Discovery\Actions\QueueDiscoveryRun;
use App\Domain\Discovery\ReadModels\BuildCandidateDecisionTable;
use App\Http\Controllers\Controller;
use App\Http\Requests\Discovery\DiscoveryCandidateTableRequest;
use App\Http\ViewModels\DiscoveryRunViewModel;
use App\Http\ViewModels\LibraryViewModel;
use App\Models\DiscoveryRun;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DiscoveryRunController extends Controller
{
    public function show(DiscoveryCandidateTableRequest $request, DiscoveryRun $discoveryRun, DiscoveryRunViewModel $viewModel, LibraryViewModel $libraryViewModel, BuildCandidateDecisionTable $candidateTable): Response
    {
        Gate::authorize('view', $discoveryRun);

        return Inertia::render('discovery/show', [
            'run' => $viewModel->toArray($discoveryRun, false),
            'candidate_table' => $candidateTable->build($discoveryRun, $request->tableQuery()),
            'library' => $libraryViewModel->context($request->user()),
            'workspaces' => $request->user()->topicWorkspaces()->whereNull('archived_at')->orderBy('name')->get(['public_id', 'name', 'market_key']),
        ]);
    }

    public function retry(Request $request, DiscoveryRun $discoveryRun, QueueDiscoveryRun $queueRun): RedirectResponse
    {
        Gate::authorize('update', $discoveryRun);
        $queueRun->handle($request->user(), $discoveryRun);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Discovery analysis queued again.')]);

        return to_route('discovery.runs.show', $discoveryRun);
    }
}
