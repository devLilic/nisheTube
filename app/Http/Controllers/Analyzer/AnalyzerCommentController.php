<?php

namespace App\Http\Controllers\Analyzer;

use App\Domain\Comments\Actions\StartCommentCollection;
use App\Http\Controllers\Controller;
use App\Models\AnalyzerRun;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final class AnalyzerCommentController extends Controller
{
    public function __invoke(Request $request, AnalyzerRun $analyzerRun, StartCommentCollection $start): RedirectResponse
    {
        Gate::authorize('collectComments', $analyzerRun);
        $run = $start->handle($request->user(), $analyzerRun);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $run->wasRecentlyCreated ? __('Public comment collection queued.') : __('Comment collection is already in progress.'),
        ]);

        return to_route('analyzer.runs.show', $analyzerRun);
    }
}
