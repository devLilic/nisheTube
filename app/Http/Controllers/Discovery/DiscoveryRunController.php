<?php

namespace App\Http\Controllers\Discovery;

use App\Domain\Discovery\Actions\QueueDiscoveryRun;
use App\Http\Controllers\Controller;
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
    public function show(Request $request, DiscoveryRun $discoveryRun, DiscoveryRunViewModel $viewModel, LibraryViewModel $libraryViewModel): Response
    {
        Gate::authorize('view', $discoveryRun);

        return Inertia::render('discovery/show', [
            'run' => $viewModel->toArray($discoveryRun),
            'library' => $libraryViewModel->context($request->user()),
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
