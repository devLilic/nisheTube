<?php

namespace App\Http\Controllers\Analyzer;

use App\Domain\Transcripts\Actions\DeleteTranscript;
use App\Domain\Transcripts\Actions\StoreUserProvidedTranscript;
use App\Http\Controllers\Controller;
use App\Http\Requests\Analyzer\StoreTranscriptRequest;
use App\Models\AnalyzerRun;
use App\Models\TranscriptDocument;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

final class AnalyzerTranscriptController extends Controller
{
    public function store(
        StoreTranscriptRequest $request,
        AnalyzerRun $analyzerRun,
        StoreUserProvidedTranscript $store,
    ): RedirectResponse {
        Gate::authorize('manageTranscripts', $analyzerRun);

        try {
            $document = $store->handle(
                $request->user(),
                $analyzerRun,
                (string) $request->validated('transcript'),
                (string) $request->validated('language'),
            );
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['transcript' => $exception->getMessage()]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $document->wasRecentlyCreated
                ? __('Transcript saved as optional evidence.')
                : __('That transcript revision is already saved.'),
        ]);

        return back();
    }

    public function destroy(
        Request $request,
        AnalyzerRun $analyzerRun,
        TranscriptDocument $transcriptDocument,
        DeleteTranscript $delete,
    ): RedirectResponse {
        Gate::authorize('manageTranscripts', $analyzerRun);
        abort_unless(
            $transcriptDocument->analyzer_run_id === $analyzerRun->id
                && $transcriptDocument->user_id === $request->user()->id,
            404,
        );
        $delete->handle($request->user(), $transcriptDocument);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Transcript revision deleted.')]);

        return back();
    }
}
