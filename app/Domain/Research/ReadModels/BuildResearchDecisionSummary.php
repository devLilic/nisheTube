<?php

namespace App\Domain\Research\ReadModels;

use App\Domain\Research\Enums\ResearchRunStatus;
use App\Models\ResearchRun;
use Carbon\CarbonImmutable;

class BuildResearchDecisionSummary
{
    /**
     * @param  array<string, mixed>|null  $score
     * @param  array<string, mixed>  $analysis
     * @param  array<string, mixed>  $evidenceProfile
     * @return array<string, mixed>
     */
    public function handle(ResearchRun $run, ?array $score, array $analysis, array $evidenceProfile = []): array
    {
        $videos = $this->rows($analysis['videos'] ?? null);
        $channels = $this->rows($analysis['channels'] ?? null);
        $summary = is_array($analysis['summary'] ?? null) ? $analysis['summary'] : [];
        $coverage = $this->coverage($run, $videos, $channels);
        $lifecycle = $this->lifecycle($run, $score, $coverage, count($videos));
        $observationTime = is_string($summary['latest_collected_at'] ?? null)
            ? $summary['latest_collected_at']
            : null;

        return [
            'lifecycle' => $lifecycle,
            'verdict' => $this->verdict($run, $score, $lifecycle['key']),
            'interpretation' => $this->interpretation($run, $score, $lifecycle['key']),
            'active_progress' => $run->status->isTerminal() ? null : [
                'stage' => $this->activeStage($run->status),
                'percent' => $run->progress_percent,
                'collected_count' => $run->collected_result_count,
                'requested_count' => $run->requested_result_count,
                'warning_count' => count($run->collection_warnings ?? []),
                'eta_label' => 'Estimated completion: not available yet',
                'eta_explanation' => 'Stage duration depends on the local queue and provider response times; NisheTube does not have enough timing evidence for a precise ETA.',
            ],
            'completeness' => $coverage,
            'stability' => $this->stability($evidenceProfile),
            'observation' => $this->observation($observationTime),
            'sample_size' => (int) ($score['sample_size'] ?? count($videos)),
            'principal_evidence' => $this->principalEvidence($score, $summary, count($videos), count($channels)),
            'risks' => $this->risks($run, $score, $coverage, $evidenceProfile),
            'next_action' => $this->nextAction($run, $score, $lifecycle['key']),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $videos
     * @param  list<array<string, mixed>>  $channels
     * @return list<array{key: string, label: string, available: int, total: int, percent: float|null, state: string}>
     */
    private function coverage(ResearchRun $run, array $videos, array $channels): array
    {
        $videoCount = count($videos);
        $channelCount = count($channels);

        return [
            $this->coverageField('collected_results', 'Search results', $run->collected_result_count, $run->requested_result_count),
            $this->coverageField('enriched_videos', 'Enriched videos', $run->enriched_result_count, $run->collected_result_count),
            $this->coverageField('public_views', 'Public views', $this->available($videos, 'view_count'), $videoCount),
            $this->coverageField('views_per_day', 'Lifetime views/day', $this->available($videos, 'views_per_day'), $videoCount),
            $this->coverageField('engagement', 'Public engagement', $this->available($videos, 'engagement_rate'), $videoCount),
            $this->coverageField('subscribers', 'Public subscribers', $this->available($channels, 'subscriber_count'), $channelCount),
        ];
    }

    /** @return array{key: string, label: string, available: int, total: int, percent: float|null, state: string} */
    private function coverageField(string $key, string $label, int $available, int $total): array
    {
        $boundedAvailable = max(0, min($available, max($total, 0)));

        return [
            'key' => $key,
            'label' => $label,
            'available' => $boundedAvailable,
            'total' => max($total, 0),
            'percent' => $total > 0 ? round(($boundedAvailable / $total) * 100, 1) : null,
            'state' => $total === 0 ? 'unavailable' : ($boundedAvailable === $total ? 'complete' : 'partial'),
        ];
    }

    /** @param list<array<string, mixed>> $rows */
    private function available(array $rows, string $field): int
    {
        return count(array_filter(
            $rows,
            fn (array $row): bool => array_key_exists($field, $row) && $row[$field] !== null,
        ));
    }

    /** @return list<array<string, mixed>> */
    private function rows(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $rows = [];
        foreach ($value as $row) {
            if (is_array($row)) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>|null  $score
     * @param  list<array<string, mixed>>  $coverage
     * @return array{key: string, label: string, description: string}
     */
    private function lifecycle(ResearchRun $run, ?array $score, array $coverage, int $videoCount): array
    {
        if (! $run->status->isTerminal()) {
            return [
                'key' => 'active',
                'label' => $this->activeStage($run->status),
                'description' => 'Collection is active. Final decision labels are withheld until the run reaches a terminal state.',
            ];
        }

        if ($run->status === ResearchRunStatus::Failed) {
            $hasPartialResults = $run->collected_result_count > 0 || $run->enriched_result_count > 0 || $videoCount > 0;

            return [
                'key' => $hasPartialResults ? 'failed_with_partial' : 'failed',
                'label' => $hasPartialResults ? 'Failed — partial results saved' : 'Failed — no results saved',
                'description' => $hasPartialResults
                    ? 'The run stopped, but stored candidates and metrics remain available for inspection.'
                    : 'The run stopped before useful evidence could be persisted.',
            ];
        }

        if ($run->status === ResearchRunStatus::Cancelled) {
            return [
                'key' => 'cancelled',
                'label' => 'Cancelled before collection',
                'description' => 'This queued attempt was cancelled before a local worker started it. No YouTube request was made.',
            ];
        }

        $hasIncompleteField = collect($coverage)->contains(
            fn (array $field): bool => $field['state'] === 'partial',
        );

        if ($score === null || ($run->collection_warnings ?? []) !== [] || $hasIncompleteField) {
            return [
                'key' => 'partial_data',
                'label' => 'Partial data',
                'description' => 'The run finished and preserved all useful stored evidence, but at least one requested field or collection segment is incomplete.',
            ];
        }

        if ((float) ($score['confidence_score'] ?? 0) < 60) {
            return [
                'key' => 'reduced_confidence',
                'label' => 'Reduced confidence',
                'description' => 'The stored fields are complete for this sample, but the scoring model identifies important confidence limitations.',
            ];
        }

        return [
            'key' => 'complete_data',
            'label' => 'Complete data',
            'description' => 'The run completed with every tracked field available for the stored sample.',
        ];
    }

    private function activeStage(ResearchRunStatus $status): string
    {
        return match ($status) {
            ResearchRunStatus::Draft => 'Preparing run',
            ResearchRunStatus::Queued => 'Waiting for local worker',
            ResearchRunStatus::Searching => 'Collecting candidates',
            ResearchRunStatus::Enriching => 'Enriching stored evidence',
            ResearchRunStatus::Scoring => 'Calculating decision summary',
            ResearchRunStatus::Completed => 'Complete',
            ResearchRunStatus::Failed => 'Failed',
            ResearchRunStatus::Cancelled => 'Cancelled',
        };
    }

    /** @param array<string, mixed>|null $score */
    private function verdict(ResearchRun $run, ?array $score, string $lifecycle): string
    {
        if (! $run->status->isTerminal()) {
            return 'Research in progress';
        }

        if ($run->status === ResearchRunStatus::Failed) {
            return $lifecycle === 'failed_with_partial'
                ? 'No final verdict; partial evidence is preserved'
                : 'No decision evidence yet';
        }

        if ($run->status === ResearchRunStatus::Cancelled) {
            return 'No decision evidence yet';
        }

        return is_string($score['overall_label'] ?? null)
            ? $score['overall_label']
            : 'No scored verdict';
    }

    /** @param array<string, mixed>|null $score */
    private function interpretation(ResearchRun $run, ?array $score, string $lifecycle): string
    {
        if (! $run->status->isTerminal()) {
            return 'NisheTube will interpret the frozen sample after collection, enrichment, and deterministic scoring finish.';
        }

        if ($run->status === ResearchRunStatus::Failed) {
            return $lifecycle === 'failed_with_partial'
                ? 'Use the saved evidence as incomplete context only. Retry creates a new immutable attempt; it does not overwrite this one.'
                : 'The run did not retain enough evidence for an opportunity decision. Retry when the blocking condition is resolved.';
        }

        if ($run->status === ResearchRunStatus::Cancelled) {
            return 'This queued attempt was stopped before collection. Start a new immutable run when you are ready to collect evidence.';
        }

        if ($score === null) {
            return 'The collection reached a terminal state without a persisted opportunity score, so no opportunity claim is supported.';
        }

        $overall = (float) ($score['overall_score'] ?? 0);
        $confidence = (float) ($score['confidence_score'] ?? 0);
        $opportunity = match (true) {
            $overall >= 80 => 'The stored sample shows strong observed opportunity signals.',
            $overall >= 65 => 'The stored sample shows promising observed opportunity signals.',
            $overall >= 50 => 'The stored sample is mixed and does not support a clear opportunity decision.',
            $overall >= 35 => 'The stored sample appears competitive or uncertain.',
            default => 'The stored sample shows weak observed opportunity signals.',
        };
        $confidenceContext = $confidence < 60
            ? ' Treat the result as directional because confidence is limited.'
            : ' Confidence is sufficient for prioritization, not for a performance guarantee.';

        return $opportunity.$confidenceContext;
    }

    /** @return array{collected_at: string|null, freshness_key: string, freshness_label: string, age_hours: int|null} */
    private function observation(?string $observedAt): array
    {
        if ($observedAt === null) {
            return [
                'collected_at' => null,
                'freshness_key' => 'unavailable',
                'freshness_label' => 'No observation yet',
                'age_hours' => null,
            ];
        }

        $ageHours = max(0, (int) floor(CarbonImmutable::parse($observedAt)->diffInSeconds(now()) / 3600));

        return [
            'collected_at' => $observedAt,
            'freshness_key' => $ageHours <= 24 ? 'fresh' : ($ageHours <= 168 ? 'aging' : 'stale'),
            'freshness_label' => $ageHours <= 24 ? 'Observed within 24 hours' : ($ageHours <= 168 ? 'Observed within 7 days' : 'Older than 7 days'),
            'age_hours' => $ageHours,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $score
     * @param  array<string, mixed>  $summary
     * @return list<array{label: string, value: string, explanation: string}>
     */
    private function principalEvidence(?array $score, array $summary, int $videoCount, int $channelCount): array
    {
        $items = [];
        $components = is_array($score['components'] ?? null) ? $score['components'] : [];
        usort($components, fn (array $left, array $right): int => ((float) ($right['score'] ?? 0)) <=> ((float) ($left['score'] ?? 0)));

        foreach (array_slice($components, 0, 3) as $component) {
            $items[] = [
                'label' => (string) ($component['label'] ?? 'Opportunity component'),
                'value' => number_format((float) ($component['score'] ?? 0), 1).' / 100',
                'explanation' => (string) ($component['explanation'] ?? 'No stored explanation is available.'),
            ];
        }

        if ($videoCount > 0) {
            $items[] = [
                'label' => 'Stored evidence sample',
                'value' => $videoCount.' videos · '.$channelCount.' channels',
                'explanation' => 'Counts reflect the exact enriched entities pinned to this immutable run.',
            ];
        }

        if (is_numeric($summary['median_views_per_day'] ?? null)) {
            $items[] = [
                'label' => 'Median lifetime views/day',
                'value' => number_format((float) $summary['median_views_per_day'], 1),
                'explanation' => 'This age-normalized median describes stored returned videos; it is not current velocity or search volume.',
            ];
        }

        return array_slice($items, 0, 5);
    }

    /**
     * @param  array<string, mixed>|null  $score
     * @param  list<array<string, mixed>>  $coverage
     * @param  array<string, mixed>  $evidenceProfile
     * @return list<string>
     */
    private function risks(ResearchRun $run, ?array $score, array $coverage, array $evidenceProfile): array
    {
        $risks = array_values(array_filter(
            $run->collection_warnings ?? [],
            fn (string $warning): bool => $warning !== '',
        ));

        foreach (is_array($score['warnings'] ?? null) ? $score['warnings'] : [] as $warning) {
            if (is_array($warning) && is_string($warning['message'] ?? null)) {
                $risks[] = $warning['message'];
            }
        }

        $incomplete = array_values(array_filter(
            $coverage,
            fn (array $field): bool => $field['state'] === 'partial',
        ));
        if ($incomplete !== []) {
            $labels = array_map(fn (array $field): string => $field['label'], $incomplete);
            $risks[] = 'Incomplete field coverage: '.implode(', ', $labels).'. Missing values are not treated as zero.';
        }

        $stability = is_array($evidenceProfile['stability'] ?? null) ? $evidenceProfile['stability'] : [];
        if (($stability['state'] ?? null) !== 'available') {
            $risks[] = (string) ($stability['reason'] ?? 'Snapshot stability is unavailable; one collection cannot establish repeatability over time.');
        }

        return array_slice(array_values(array_unique($risks)), 0, 5);
    }

    /**
     * @param  array<string, mixed>  $profile
     * @return array{key: string, label: string, description: string}
     */
    private function stability(array $profile): array
    {
        $stability = is_array($profile['stability'] ?? null) ? $profile['stability'] : [];
        $label = is_string($stability['label'] ?? null) ? $stability['label'] : null;

        return [
            'key' => $label ?? 'not_measured',
            'label' => $label === null ? 'Not measured' : ucfirst($label).' stability',
            'description' => $label === null
                ? (string) ($stability['reason'] ?? 'No compatible earlier snapshot is available.')
                : 'Based on compatible result/channel overlap, order stability, metric variance, and median variation. Opportunity does not imply stability.',
        ];
    }

    /**
     * @param  array<string, mixed>|null  $score
     * @return array{kind: string, label: string, description: string}
     */
    private function nextAction(ResearchRun $run, ?array $score, string $lifecycle): array
    {
        if (! $run->status->isTerminal()) {
            return [
                'kind' => 'wait',
                'label' => 'Wait for the terminal result',
                'description' => 'Keep the local queue worker running. This page refreshes stored progress automatically.',
            ];
        }

        if ($run->status === ResearchRunStatus::Failed) {
            if ($run->error_code === 'youtube_request_invalid') {
                return [
                    'kind' => 'new_search',
                    'label' => 'Adjust filters in a new validation',
                    'description' => 'YouTube rejected the frozen filters. Keep this attempt for history and create a corrected validation.',
                ];
            }

            return [
                'kind' => 'retry',
                'label' => 'Retry as a new attempt',
                'description' => 'Resolve the safe error guidance first. Retrying preserves this attempt and its partial results.',
            ];
        }

        if ($run->status === ResearchRunStatus::Cancelled) {
            return [
                'kind' => 'new_search',
                'label' => 'Start a new research run',
                'description' => 'This cancelled attempt remains in history. Starting again creates a separate immutable attempt.',
            ];
        }

        if ($score === null || in_array($lifecycle, ['partial_data', 'reduced_confidence'], true)) {
            return [
                'kind' => 'new_search',
                'label' => 'Validate with another snapshot',
                'description' => 'Use a deeper or later validation to reduce missing-data and single-snapshot uncertainty.',
            ];
        }

        return [
            'kind' => 'review_evidence',
            'label' => 'Review the principal evidence',
            'description' => 'Confirm the strongest components and named risks before saving or comparing this niche.',
        ];
    }
}
