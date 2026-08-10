<?php

namespace App\Http\Controllers\Analyzer;

use App\Domain\Audience\Actions\ManageAudienceSignalExclusion;
use App\Http\Controllers\Controller;
use App\Http\Requests\Analyzer\StoreAudienceSignalExclusionRequest;
use App\Models\AnalyzerRun;
use App\Models\AudienceSignalExclusion;
use App\Models\User;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final class AnalyzerAudienceSignalExclusionController extends Controller
{
    public function store(
        StoreAudienceSignalExclusionRequest $request,
        AnalyzerRun $analyzerRun,
        ManageAudienceSignalExclusion $manage,
    ): RedirectResponse {
        Gate::authorize('view', $analyzerRun);
        /** @var User $user */
        $user = $request->user();

        try {
            $manage->exclude($user, $analyzerRun, $request->word());
            Inertia::flash('toast', ['type' => 'success', 'message' => __('The word was hidden from your Audience Signals.')]);
        } catch (DomainException $exception) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __($exception->getMessage())]);
        }

        return to_route('analyzer.runs.show', $analyzerRun);
    }

    public function destroy(
        Request $request,
        AnalyzerRun $analyzerRun,
        AudienceSignalExclusion $audienceSignalExclusion,
        ManageAudienceSignalExclusion $manage,
    ): RedirectResponse {
        Gate::authorize('view', $analyzerRun);
        /** @var User $user */
        $user = $request->user();

        try {
            $manage->restore($user, $audienceSignalExclusion);
            Inertia::flash('toast', ['type' => 'success', 'message' => __('The word is visible in Audience Signals again.')]);
        } catch (DomainException $exception) {
            abort(403, $exception->getMessage());
        }

        return to_route('analyzer.runs.show', $analyzerRun);
    }
}
