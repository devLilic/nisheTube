<?php

namespace App\Http\Controllers\Analyzer;

use App\Domain\Audience\Actions\CalculateLatestAudienceSignals;
use App\Http\Controllers\Controller;
use App\Models\AnalyzerRun;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final class AnalyzerAudienceSignalController extends Controller
{
    public function __invoke(Request $request, AnalyzerRun $analyzerRun, CalculateLatestAudienceSignals $calculate): RedirectResponse
    {
        Gate::authorize('analyzeAudienceSignals', $analyzerRun);

        try {
            $calculate->handle($request->user(), $analyzerRun);
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Audience Signals calculated from the stored comment sample.')]);
        } catch (DomainException $exception) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __($exception->getMessage())]);
        }

        return to_route('analyzer.runs.show', $analyzerRun);
    }
}
