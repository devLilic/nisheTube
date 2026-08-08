<?php

namespace App\Domain\History\Services;

use App\Domain\Research\Enums\ResearchRunStatus;
use App\Models\OpportunityScore;
use App\Models\ResearchRun;
use App\Models\User;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;

class ResearchRunCompatibility
{
    /** @throws AuthorizationException */
    public function authorize(User $user, ResearchRun ...$runs): void
    {
        foreach ($runs as $run) {
            if ($run->user_id !== $user->id) {
                throw new AuthorizationException;
            }
        }
    }

    /** @throws DomainException */
    public function assertComparable(ResearchRun $before, ResearchRun $after): void
    {
        if ($before->is($after)) {
            throw new DomainException('Choose two different research runs to compare.');
        }

        if ($before->status !== ResearchRunStatus::Completed || $after->status !== ResearchRunStatus::Completed) {
            throw new DomainException('Only completed research runs can be compared.');
        }

        if ($this->queryKey($before) !== $this->queryKey($after)) {
            throw new DomainException('Research runs must use the same normalized query.');
        }

        if (
            $before->market_key !== $after->market_key
            || $before->region_code !== $after->region_code
            || $before->relevance_language !== $after->relevance_language
        ) {
            throw new DomainException('Research runs must use the same frozen market mapping.');
        }

        if ($before->kind !== $after->kind) {
            throw new DomainException('Research runs must have the same collection kind.');
        }
    }

    public function queryKey(ResearchRun $run): string
    {
        return Str::lower(Str::squish($run->query_text));
    }

    /**
     * @return list<array{field: string, before: mixed, after: mixed}>
     */
    public function parameterChanges(ResearchRun $before, ResearchRun $after): array
    {
        $beforeParameters = $before->parameters;
        $afterParameters = $after->parameters;
        $fields = array_values(array_unique(array_merge(array_keys($beforeParameters), array_keys($afterParameters))));
        sort($fields);
        $changes = [];

        foreach ($fields as $field) {
            $beforeValue = $beforeParameters[$field] ?? null;
            $afterValue = $afterParameters[$field] ?? null;

            if ($beforeValue !== $afterValue) {
                $changes[] = [
                    'field' => $field,
                    'before' => $beforeValue,
                    'after' => $afterValue,
                ];
            }
        }

        if ($before->requested_result_count !== $after->requested_result_count) {
            $changes[] = [
                'field' => 'requested_result_count',
                'before' => $before->requested_result_count,
                'after' => $after->requested_result_count,
            ];
        }

        usort($changes, fn (array $left, array $right): int => $left['field'] <=> $right['field']);

        return $changes;
    }

    /**
     * @return list<array{code: string, message: string}>
     */
    public function warnings(
        ResearchRun $before,
        ResearchRun $after,
        ?OpportunityScore $beforeScore,
        ?OpportunityScore $afterScore,
    ): array {
        $warnings = [];

        if ($this->parameterChanges($before, $after) !== []) {
            $warnings[] = [
                'code' => 'collection_parameters_differ',
                'message' => 'Collection parameters differ, so metric changes may reflect sampling differences.',
            ];
        }

        if ($beforeScore === null || $afterScore === null) {
            $warnings[] = [
                'code' => 'score_missing',
                'message' => 'At least one run has no stored score, so score and component deltas are unavailable.',
            ];
        } elseif ($beforeScore->formula_version !== $afterScore->formula_version) {
            $warnings[] = [
                'code' => 'formula_version_differs',
                'message' => 'Score formula versions differ, so score and component deltas are not directly comparable.',
            ];
        }

        if (($before->collection_warnings ?? []) !== [] || ($after->collection_warnings ?? []) !== []) {
            $warnings[] = [
                'code' => 'partial_collection',
                'message' => 'At least one run contains partial-collection warnings.',
            ];
        }

        return $warnings;
    }

    public function scoresComparable(?OpportunityScore $beforeScore, ?OpportunityScore $afterScore): bool
    {
        return $beforeScore !== null
            && $afterScore !== null
            && $beforeScore->formula_version === $afterScore->formula_version;
    }
}
