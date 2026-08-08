<?php

namespace App\Http\Controllers\History;

use App\Domain\History\ReadModels\BuildResearchRunComparison;
use App\Domain\History\Services\ResearchRunCompatibility;
use App\Http\Controllers\Controller;
use App\Http\ViewModels\HistoryViewModel;
use App\Models\ResearchRun;
use App\Models\User;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class HistoryController extends Controller
{
    public function index(Request $request, HistoryViewModel $viewModel): Response
    {
        Gate::authorize('viewAny', ResearchRun::class);
        /** @var User $user */
        $user = $request->user();
        $anchor = $request->string('anchor')->toString() ?: null;

        return Inertia::render('history/index', [
            'history' => Inertia::defer(
                fn (): array => $viewModel->index($user, $anchor),
                rescue: true,
            ),
        ]);
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
