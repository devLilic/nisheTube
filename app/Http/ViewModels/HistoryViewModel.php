<?php

namespace App\Http\ViewModels;

use App\Domain\History\ReadModels\ListComparableResearchRuns;
use App\Domain\Research\Enums\ResearchRunStatus;
use App\Domain\Settings\Enums\MarketKey;
use App\Models\OpportunityScore;
use App\Models\ResearchRun;
use App\Models\User;

class HistoryViewModel
{
    private const RUN_LIMIT = 100;

    public function __construct(private readonly ListComparableResearchRuns $comparableRuns) {}

    /** @return array<string, mixed> */
    public function index(User $user, ?string $anchorPublicId): array
    {
        $runs = ResearchRun::query()
            ->where('user_id', $user->id)
            ->with(['opportunityScores' => fn ($query) => $query
                ->latest('calculated_at')
                ->latest('id')])
            ->latest('created_at')
            ->latest('id')
            ->limit(self::RUN_LIMIT)
            ->get();

        $anchor = $anchorPublicId === null
            ? null
            : $runs->firstWhere('public_id', $anchorPublicId);

        if ($anchorPublicId !== null && $anchor === null) {
            $anchor = ResearchRun::query()
                ->where('user_id', $user->id)
                ->where('public_id', $anchorPublicId)
                ->firstOrFail();
        }

        return [
            'runs' => $runs->map(fn (ResearchRun $run): array => $this->run($run))->values()->all(),
            'selected_anchor' => $anchor?->public_id,
            'candidates' => $anchor === null || $anchor->status !== ResearchRunStatus::Completed
                ? []
                : $this->comparableRuns->handle($user, $anchor),
            'truncated' => $runs->count() === self::RUN_LIMIT,
        ];
    }

    /** @return array<string, mixed> */
    public function pair(ResearchRun $before, ResearchRun $after): array
    {
        return [
            'before' => $this->run($before),
            'after' => $this->run($after),
        ];
    }

    /** @return array<string, mixed> */
    private function run(ResearchRun $run): array
    {
        $score = $run->relationLoaded('opportunityScores')
            ? $run->opportunityScores->first()
            : $run->opportunityScores()->latest('calculated_at')->latest('id')->first();

        return [
            'public_id' => $run->public_id,
            'query_text' => $run->query_text,
            'market_key' => $run->market_key,
            'market_name' => MarketKey::tryFrom($run->market_key)?->label() ?? $run->market_key,
            'kind' => $run->kind->value,
            'status' => $run->status->value,
            'attempt_number' => $run->attempt_number,
            'parameters' => $run->parameters,
            'requested_result_count' => $run->requested_result_count,
            'collected_result_count' => $run->collected_result_count,
            'collection_warnings' => $run->collection_warnings ?? [],
            'created_at' => $run->created_at?->toIso8601String(),
            'completed_at' => $run->completed_at?->toIso8601String(),
            'failed_at' => $run->failed_at?->toIso8601String(),
            'score' => $this->score($score),
            'can_compare' => $run->status === ResearchRunStatus::Completed,
        ];
    }

    /** @return array<string, mixed>|null */
    private function score(?OpportunityScore $score): ?array
    {
        if ($score === null) {
            return null;
        }

        return [
            'overall_score' => (float) $score->overall_score,
            'confidence_score' => (float) $score->confidence_score,
            'formula_version' => $score->formula_version,
            'calculated_at' => $score->calculated_at->toIso8601String(),
        ];
    }
}
