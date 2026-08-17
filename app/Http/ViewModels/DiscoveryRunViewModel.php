<?php

namespace App\Http\ViewModels;

use App\Domain\Discovery\Enums\DiscoveryRunStatus;
use App\Models\DiscoveryRun;
use App\Models\DiscoverySeed;
use App\Models\NicheCandidate;

class DiscoveryRunViewModel
{
    /** @return array<string, mixed> */
    public function toArray(DiscoveryRun $run, bool $withCandidates = true): array
    {
        $run->loadMissing(['market', 'seeds.researchRun']);

        if ($withCandidates) {
            $run->loadMissing(['candidates.validationResearchRun']);
        }

        return [
            'public_id' => $run->public_id,
            'status' => $run->status->value,
            'market' => [
                'key' => $run->market_key,
                'name' => $run->market->name,
            ],
            'parameters' => [
                'sample_per_seed' => (int) ($run->parameters['sample_per_seed'] ?? 25),
                'candidate_limit' => (int) ($run->parameters['candidate_limit'] ?? 20),
                'formula_version' => (string) ($run->parameters['formula_version'] ?? 'discovery-breakout-v1'),
                'candidate_evidence_thresholds' => $run->parameters['candidate_evidence_thresholds'] ?? null,
                'language' => (string) ($run->parameters['language'] ?? $run->relevance_language),
                'content_format' => (string) ($run->parameters['content_format'] ?? 'any'),
                'period' => (string) ($run->parameters['period'] ?? 'past_three_months'),
                'target_channel_size' => (string) ($run->parameters['target_channel_size'] ?? 'any'),
            ],
            'seed_count' => $run->seed_count,
            'candidate_count' => $run->candidate_count,
            'progress_percent' => $run->progress_percent,
            'created_at' => $run->created_at?->toIso8601String(),
            'started_at' => $run->started_at?->toIso8601String(),
            'completed_at' => $run->completed_at?->toIso8601String(),
            'failed_at' => $run->failed_at?->toIso8601String(),
            'is_active' => ! $run->status->isTerminal(),
            'can_retry' => $run->status === DiscoveryRunStatus::Failed,
            'error' => $run->status === DiscoveryRunStatus::Failed ? [
                'code' => $run->error_code ?? 'discovery_generation_failed',
                'message' => $run->error_message ?? 'Discovery analysis could not finish.',
            ] : null,
            'partial_warnings' => $run->seeds
                ->flatMap(function (DiscoverySeed $seed): array {
                    if ($seed->research_run_id === null) {
                        return [];
                    }

                    return array_map(
                        fn (string $warning): string => "Seed \"{$seed->seed_query}\" used a partial sample: {$warning}",
                        $seed->researchRun->collection_warnings ?? [],
                    );
                })
                ->values()
                ->all(),
            'seeds' => $run->seeds->map(fn (DiscoverySeed $seed): array => [
                'query' => $seed->seed_query,
                'source' => $seed->source->value,
                'research_run' => $seed->researchRun === null ? null : [
                    'public_id' => $seed->researchRun->public_id,
                    'query_text' => $seed->researchRun->query_text,
                    'completed_at' => $seed->researchRun->completed_at?->toIso8601String(),
                ],
            ])->values()->all(),
            'candidates' => $withCandidates
                ? $run->candidates
                    ->sortBy(fn (NicheCandidate $candidate): string => sprintf(
                        '%d-%010.4f-%s',
                        $candidate->evidence_state->value === 'weak_phrase_signal' ? 1 : 0,
                        100 - ($candidate->overall_score ?? 0),
                        $candidate->phrase,
                    ))
                    ->map(fn (NicheCandidate $candidate): array => [
                        'public_id' => $candidate->public_id,
                        'phrase' => $candidate->phrase,
                        'summary' => $candidate->summary,
                        'status' => $candidate->status->value,
                        'overall_score' => $candidate->overall_score,
                        'confidence_score' => $candidate->confidence_score,
                        'formula_version' => $candidate->formula_version,
                        'evidence_state' => $candidate->evidence_state->value,
                        'evidence' => $candidate->evidence,
                        'validation_run' => $candidate->validationResearchRun === null ? null : [
                            'public_id' => $candidate->validationResearchRun->public_id,
                            'status' => $candidate->validationResearchRun->status->value,
                        ],
                    ])->values()->all()
                : [],
        ];
    }
}
