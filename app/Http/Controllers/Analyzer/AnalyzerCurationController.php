<?php

namespace App\Http\Controllers\Analyzer;

use App\Domain\Analyzer\Actions\UpdateAnalyzerCuration;
use App\Http\Controllers\Controller;
use App\Http\Requests\Analyzer\UpdateAnalyzerCurationRequest;
use App\Models\AnalyzerRun;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class AnalyzerCurationController extends Controller
{
    public function update(
        UpdateAnalyzerCurationRequest $request,
        AnalyzerRun $analyzerRun,
        UpdateAnalyzerCuration $updateCuration,
    ): RedirectResponse {
        Gate::authorize('view', $analyzerRun);
        $updateCuration->handle(
            $request->user(),
            $analyzerRun,
            (string) $request->validated('subject_type'),
            (string) $request->validated('research_status'),
            $request->note(),
        );
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Analyzer curation saved.')]);

        return back();
    }
}
