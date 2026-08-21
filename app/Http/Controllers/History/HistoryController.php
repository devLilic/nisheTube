<?php

namespace App\Http\Controllers\History;

use App\Domain\History\Actions\RepeatResearchRun;
use App\Domain\History\ReadModels\BuildResearchRunComparison;
use App\Domain\History\Services\ResearchRunCompatibility;
use App\Http\Controllers\Controller;
use App\Http\Requests\History\HistoryIndexRequest;
use App\Http\Requests\History\RepeatResearchRunRequest;
use App\Http\ViewModels\HistoryViewModel;
use App\Models\ResearchRun;
use App\Models\User;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class HistoryController extends Controller
{
    public function index(HistoryIndexRequest $request, HistoryViewModel $viewModel): Response
    {
        Gate::authorize('viewAny', ResearchRun::class);
        /** @var User $user */
        $user = $request->user();
        $anchor = $request->string('anchor')->toString() ?: null;

        return Inertia::render('history/index', [
            'history' => Inertia::defer(
                fn (): array => $viewModel->index($user, $anchor, $request->historyFilters()),
                rescue: true,
            ),
        ]);
    }

    public function repeat(
        RepeatResearchRunRequest $request,
        ResearchRun $researchRun,
        RepeatResearchRun $repeatResearchRun,
    ): RedirectResponse {
        Gate::authorize('view', $researchRun);

        $repeat = $repeatResearchRun->handle(
            $request->user(),
            $researchRun,
            $request->submissionToken(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Research run queued with the confirmed frozen parameters.')]);

        return to_route('research.runs.show', $repeat);
    }

    public function compare(
        Request $request,
        ResearchRun $beforeRun,
        ResearchRun $afterRun,
        ResearchRunCompatibility $compatibility,
        BuildResearchRunComparison $comparison,
        HistoryViewModel $viewModel,
    ): Response {
        Gate::authorize('view', $beforeRun);
        Gate::authorize('view', $afterRun);

        try {
            $compatibility->assertComparable($beforeRun, $afterRun);
        } catch (DomainException $exception) {
            abort(422, $exception->getMessage());
        }

        /** @var User $user */
        $user = $request->user();

        return Inertia::render('history/compare', [
            'pair' => $viewModel->pair($beforeRun, $afterRun),
            'comparison' => Inertia::defer(
                fn (): array => $comparison->handle($user, $beforeRun, $afterRun),
                rescue: true,
            ),
        ]);
    }
}
