<?php

namespace App\Domain\Library\ReadModels;

use App\Domain\Library\Enums\LibraryTargetType;
use App\Models\Favorite;
use App\Models\ResearchRun;
use App\Models\User;

class BuildShortlistComparison
{
    /**
     * @param  list<string>  $selectedPublicIds
     * @return array<string, mixed>
     */
    public function handle(User $user, array $selectedPublicIds): array
    {
        $items = Favorite::query()->forUser($user)
            ->where('target_type', LibraryTargetType::ResearchRun->value)
            ->with(['target' => fn ($query) => $query->with(['opportunityScores' => fn ($scores) => $scores->latest('calculated_at')->latest('id'), 'profitabilityFitScores' => fn ($fits) => $fits->latest('calculated_at')->latest('id')])])
            ->latest('updated_at')->get()
            ->map(function (Favorite $favorite): ?array {
                $run = $favorite->target instanceof ResearchRun ? $favorite->target : null;
                if ($run === null) {
                    return null;
                }
                $score = $run->opportunityScores->first();
                $fit = $run->profitabilityFitScores->first();

                return ['public_id' => $run->public_id, 'query_text' => $run->query_text, 'score' => $score === null ? null : ['overall_score' => (float) $score->overall_score, 'confidence_score' => (float) $score->confidence_score, 'formula_version' => $score->formula_version], 'profitability_fit' => $fit === null ? null : ['fit_score' => (float) $fit->fit_score, 'formula_version' => $fit->formula_version]];
            })->filter()->values();
        $selected = $items->filter(fn (array $item): bool => in_array($item['public_id'], $selectedPublicIds, true))->values();
        $versions = $selected->pluck('score.formula_version')->filter()->unique();
        $missing = $selected->contains(fn (array $item): bool => $item['score'] === null);
        $state = $selected->count() < 2 ? 'needs_more' : ($missing ? 'partial' : ($versions->count() > 1 ? 'incompatible' : 'ready'));

        return ['items' => $items->all(), 'selected' => $selected->all(), 'selection' => ['minimum' => 2, 'maximum' => 5, 'count' => $selected->count(), 'state' => $state, 'message' => match ($state) {
            'needs_more' => 'Select two to five saved Research runs to compare their frozen evidence.', 'partial' => 'One or more selected runs has no stored opportunity score, so exact score comparison is unavailable.', 'incompatible' => 'Selected runs use incompatible opportunity-score versions. Review each stored result instead of comparing scores directly.', default => 'Selected runs use compatible stored score versions.'
        }]];
    }
}
