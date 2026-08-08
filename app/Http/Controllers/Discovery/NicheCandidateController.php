<?php

namespace App\Http\Controllers\Discovery;

use App\Domain\Discovery\Actions\StartCandidateValidation;
use App\Domain\Discovery\Actions\UpdateCandidateStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Discovery\UpdateCandidateStatusRequest;
use App\Http\Requests\Discovery\ValidateCandidateRequest;
use App\Models\NicheCandidate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class NicheCandidateController extends Controller
{
    public function update(
        UpdateCandidateStatusRequest $request,
        NicheCandidate $nicheCandidate,
        UpdateCandidateStatus $updateStatus,
    ): RedirectResponse {
        Gate::authorize('update', $nicheCandidate);
        $updateStatus->handle($request->user(), $nicheCandidate, $request->status());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Candidate status updated.')]);

        return back();
    }

    public function validateCandidate(
        ValidateCandidateRequest $request,
        NicheCandidate $nicheCandidate,
        StartCandidateValidation $startValidation,
    ): RedirectResponse {
        Gate::authorize('update', $nicheCandidate);

        if ($nicheCandidate->validation_research_run_id !== null) {
            throw ValidationException::withMessages([
                'candidate' => __('This candidate already has a validation research run.'),
            ]);
        }

        $run = $startValidation->handle($request->user(), $nicheCandidate, $request->requestedResultCount());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Validation research queued.')]);

        return to_route('research.runs.show', $run);
    }
}
