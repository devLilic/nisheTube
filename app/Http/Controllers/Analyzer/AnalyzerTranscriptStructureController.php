<?php

namespace App\Http\Controllers\Analyzer;

use App\Domain\Transcripts\Actions\CalculateTranscriptStructure;
use App\Http\Controllers\Controller;
use App\Models\AnalyzerRun;
use App\Models\TranscriptDocument;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final class AnalyzerTranscriptStructureController extends Controller
{
    public function __invoke(
        Request $request,
        AnalyzerRun $analyzerRun,
        TranscriptDocument $transcriptDocument,
        CalculateTranscriptStructure $calculate,
    ): RedirectResponse {
        Gate::authorize('manageTranscripts', $analyzerRun);
        abort_unless(
            $transcriptDocument->user_id === $request->user()->id
                && $transcriptDocument->analyzer_run_id === $analyzerRun->id,
            404,
        );

        try {
            $profile = $calculate->handle($request->user(), $analyzerRun, $transcriptDocument);
            $message = $profile->status === 'failed'
                ? 'Transcript structure analysis failed safely. The original transcript remains available.'
                : 'Transcript structure analyzed from the selected stored revision.';
            Inertia::flash('toast', ['type' => $profile->status === 'failed' ? 'error' : 'success', 'message' => __($message)]);
        } catch (DomainException $exception) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __($exception->getMessage())]);
        }

        return to_route('analyzer.runs.show', $analyzerRun);
    }
}
