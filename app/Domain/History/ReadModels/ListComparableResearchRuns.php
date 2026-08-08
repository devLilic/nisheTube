<?php

namespace App\Domain\History\ReadModels;

use App\Domain\History\Services\ResearchRunCompatibility;
use App\Domain\Research\Enums\ResearchRunStatus;
use App\Models\OpportunityScore;
use App\Models\ResearchRun;
use App\Models\User;

class ListComparableResearchRuns
{
    private const RESULT_LIMIT = 100;

    public function __construct(private readonly ResearchRunCompatibility $compatibility) {}

    /** @return list<array<string, mixed>> */
    public function handle(User $user, ResearchRun $anchor): array
    {
        $this->compatibility->authorize($user, $anchor);

        $anchorScore = $this->latestScore($anchor);
        $queryKey = $this->compatibility->queryKey($anchor);

        return array_values(ResearchRun::query()
            ->where('user_id', $user->id)
            ->whereKeyNot($anchor->id)
            ->where('status', ResearchRunStatus::Completed->value)
            ->where('kind', $anchor->kind->value)
            ->where('market_key', $anchor->market_key)
            ->where('region_code', $anchor->region_code)
            ->where('relevance_language', $anchor->relevance_language)
            ->whereRaw('LOWER(query_text) = ?', [$queryKey])
            ->with(['opportunityScores' => fn ($query) => $query
                ->latest('calculated_at')
                ->latest('id')])
            ->latest('completed_at')
            ->latest('id')
            ->limit(self::RESULT_LIMIT)
            ->get()
            ->map(function (ResearchRun $candidate) use ($anchor, $anchorScore): array {
                $candidateScore = $candidate->opportunityScores->first();
                $warnings = $this->compatibility->warnings($anchor, $candidate, $anchorScore, $candidateScore);

                return [
                    'public_id' => $candidate->public_id,
                    'completed_at' => $candidate->completed_at?->toIso8601String(),
                    'requested_result_count' => $candidate->requested_result_count,
                    'collected_result_count' => $candidate->collected_result_count,
                    'formula_version' => $candidateScore?->formula_version,
                    'overall_score' => $candidateScore === null ? null : (float) $candidateScore->overall_score,
                    'confidence_score' => $candidateScore === null ? null : (float) $candidateScore->confidence_score,
                    'score_comparable' => $this->compatibility->scoresComparable($anchorScore, $candidateScore),
                    'warning_codes' => array_column($warnings, 'code'),
                ];
            })
            ->values()
            ->all());
    }

    private function latestScore(ResearchRun $run): ?OpportunityScore
    {
        return $run->opportunityScores()
            ->latest('calculated_at')
            ->latest('id')
            ->first();
    }
}
