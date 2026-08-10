<?php

namespace App\Http\Controllers\Analyzer;

use App\Domain\Analyzer\ReadModels\BuildCrossChannelComparison;
use App\Domain\Analyzer\ReadModels\ListComparableChannelAnalyses;
use App\Http\Controllers\Controller;
use App\Http\Requests\Analyzer\CompareAnalyzerChannelsRequest;
use App\Models\AnalyzerRun;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AnalyzerComparisonController extends Controller
{
    public function __invoke(
        CompareAnalyzerChannelsRequest $request,
        ListComparableChannelAnalyses $list,
        BuildCrossChannelComparison $comparison,
    ): Response {
        Gate::authorize('viewAny', AnalyzerRun::class);
        /** @var User $user */
        $user = $request->user();
        $selectedIds = $request->selectedIds();
        $selected = null;

        if (count($selectedIds) >= 2) {
            $runs = AnalyzerRun::query()
                ->where('user_id', $user->id)
                ->whereIn('public_id', $selectedIds)
                ->get()
                ->keyBy('public_id');
            abort_unless($runs->count() === count($selectedIds), 404);

            try {
                $orderedRuns = collect($selectedIds)->map(fn (string $id): AnalyzerRun => $runs->get($id));
                $selected = $comparison->handle($user, ...$orderedRuns->all());
            } catch (DomainException $exception) {
                abort(422, $exception->getMessage());
            }
        }

        return Inertia::render('analyzer/compare', [
            'options' => $list->handle($user),
            'selected' => $selected,
            'selection' => ['runs' => $selectedIds],
        ]);
    }
}
