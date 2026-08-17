<?php

namespace App\Domain\Scoring\Services;

use App\Domain\Discovery\Services\MultilingualPhraseNormalizer;
use App\Domain\Scoring\Data\ResearchEvidenceVideoInput;

final readonly class ResearchEvidenceV1
{
    public const VERSION = 'research-evidence-v1';

    public const MIN_ROBUST_SAMPLE = 3;

    public const MIN_TRIMMED_SAMPLE = 5;

    public const MIN_STABILITY_OVERLAP = 3;

    public function __construct(
        private MultilingualPhraseNormalizer $normalizer,
        private RobustStatistics $statistics,
    ) {}

    /**
     * @param  list<ResearchEvidenceVideoInput>  $videos
     * @return array{rows: list<array<string, mixed>>, sample_evidence: array<string, mixed>, format_evidence: array<string, mixed>, outlier_evidence: array<string, mixed>, warnings: list<string>}
     */
    public function calculate(string $query, string $expectedLanguage, string $requestedFormat, array $videos): array
    {
        $positiveQuery = preg_replace('/(?:^|\s)-[\p{L}\p{N}_-]+/u', ' ', $query) ?? $query;
        preg_match_all('/(?:^|\s)-([\p{L}\p{N}_-]+)/u', $query, $negativeMatches);
        $queryPhrase = $this->normalizer->normalize($positiveQuery);
        $negativeTokens = array_values(array_unique(array_map(
            fn (string $token): string => $this->normalizer->normalize($token)->tokens[0] ?? mb_strtolower($token),
            $negativeMatches[1],
        )));

        $rows = array_map(
            fn (ResearchEvidenceVideoInput $video): array => $this->classify(
                $video,
                $queryPhrase->tokens,
                $queryPhrase->key,
                $negativeTokens,
                $expectedLanguage,
                $requestedFormat,
            ),
            $videos,
        );

        $strictRows = array_values(array_filter($rows, fn (array $row): bool => $row['relevance_class'] === 'strictly_relevant'));
        $formatEvidence = [];
        foreach (['shorts', 'long_form', 'unknown'] as $format) {
            $formatRows = array_values(array_filter($rows, fn (array $row): bool => $row['format_class'] === $format));
            $formatEvidence[$format] = $this->sample($formatRows);
        }

        $warnings = [];
        if ($queryPhrase->tokens === []) {
            $warnings[] = 'The query has no usable normalized terms, so strict relevance cannot be established.';
        }
        if (count($rows) < self::MIN_ROBUST_SAMPLE) {
            $warnings[] = 'Fewer than three enriched results are available; robust outlier evidence is insufficient.';
        }
        if ($formatEvidence['unknown']['count'] > 0) {
            $warnings[] = 'Some results have unknown format and are excluded from format-specific claims.';
        }
        if ($formatEvidence['shorts']['count'] > 0 && $formatEvidence['long_form']['count'] > 0) {
            $warnings[] = 'Shorts and long-form evidence are reported separately and are not compared directly.';
        }
        if ($strictRows === []) {
            $warnings[] = 'No result met the frozen strictly-relevant threshold; strict-sample statistics are unavailable.';
        }

        return [
            'rows' => $rows,
            'sample_evidence' => [
                'full' => $this->sample($rows),
                'strict' => $this->sample($strictRows),
                'class_counts' => $this->classCounts($rows),
            ],
            'format_evidence' => $formatEvidence,
            'outlier_evidence' => [
                'full' => $this->outliers($rows),
                'strict' => $this->outliers($strictRows),
            ],
            'warnings' => $warnings,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $currentRows
     * @param  list<array<string, mixed>>  $previousRows
     * @return array<string, mixed>
     */
    public function stability(array $currentRows, array $previousRows): array
    {
        if ($previousRows === []) {
            return $this->unavailableStability('No earlier compatible evidence snapshot is available.');
        }

        $currentById = collect($currentRows)->keyBy('provider_video_id');
        $previousById = collect($previousRows)->keyBy('provider_video_id');
        $commonIds = $currentById->keys()->intersect($previousById->keys())->values()->all();
        $unionCount = $currentById->keys()->merge($previousById->keys())->unique()->count();
        $resultOverlap = $unionCount > 0 ? count($commonIds) / $unionCount : null;

        $currentChannels = collect($currentRows)->pluck('channel_id')->unique();
        $previousChannels = collect($previousRows)->pluck('channel_id')->unique();
        $channelUnion = $currentChannels->merge($previousChannels)->unique()->count();
        $channelOverlap = $channelUnion > 0 ? $currentChannels->intersect($previousChannels)->count() / $channelUnion : null;

        if (count($commonIds) < self::MIN_STABILITY_OVERLAP) {
            return [
                ...$this->unavailableStability('Fewer than three videos overlap with the earlier compatible snapshot.'),
                'previous_overlap_count' => count($commonIds),
                'result_overlap' => $this->rounded($resultOverlap),
                'channel_overlap' => $this->rounded($channelOverlap),
            ];
        }

        $currentPositions = collect($currentRows)
            ->whereIn('provider_video_id', $commonIds)
            ->sortBy('result_rank')->values()->pluck('provider_video_id')
            ->flip()->map(fn (int $index): int => $index + 1);
        $previousPositions = collect($previousRows)
            ->whereIn('provider_video_id', $commonIds)
            ->sortBy('result_rank')->values()->pluck('provider_video_id')
            ->flip()->map(fn (int $index): int => $index + 1);
        $currentRanks = [];
        $previousRanks = [];
        $metricChanges = [];
        foreach ($commonIds as $id) {
            $current = $currentById->get($id);
            $previous = $previousById->get($id);
            $currentRanks[] = (float) $currentPositions->get($id);
            $previousRanks[] = (float) $previousPositions->get($id);
            $currentMetric = $current['views_per_day'];
            $previousMetric = $previous['views_per_day'];
            if (is_numeric($currentMetric) && is_numeric($previousMetric) && (float) $previousMetric > 0) {
                $metricChanges[] = abs(((float) $currentMetric - (float) $previousMetric) / (float) $previousMetric);
            }
        }

        $orderStability = $this->spearman($currentRanks, $previousRanks);
        $metricVariance = $this->statistics->median($metricChanges);
        $currentMedian = $this->statistics->median($this->metricValues($currentRows));
        $previousMedian = $this->statistics->median($this->metricValues($previousRows));
        $medianVariation = $currentMedian !== null && $previousMedian !== null && $previousMedian > 0
            ? abs(($currentMedian - $previousMedian) / $previousMedian)
            : null;

        $complete = $orderStability !== null && $metricVariance !== null && $medianVariation !== null;
        $label = ! $complete ? null : match (true) {
            $resultOverlap >= 0.70 && $channelOverlap >= 0.70 && $orderStability >= 0.70 && $metricVariance <= 0.20 && $medianVariation <= 0.20 => 'high',
            $resultOverlap >= 0.40 && $channelOverlap >= 0.40 && $orderStability >= 0.30 && $metricVariance <= 0.50 && $medianVariation <= 0.50 => 'medium',
            default => 'low',
        };

        return [
            'state' => $complete ? 'available' : 'insufficient_metrics',
            'label' => $label,
            'reason' => $complete ? null : 'Overlapping rows lack enough positive compatible metric values.',
            'previous_overlap_count' => count($commonIds),
            'metric_pair_count' => count($metricChanges),
            'result_overlap' => $this->rounded($resultOverlap),
            'channel_overlap' => $this->rounded($channelOverlap),
            'order_stability' => $this->rounded($orderStability),
            'metric_variance' => $this->rounded($metricVariance),
            'median_variation' => $this->rounded($medianVariation),
        ];
    }

    /**
     * @param  list<string>  $queryTokens
     * @param  list<string>  $negativeTokens
     * @return array<string, mixed>
     */
    private function classify(ResearchEvidenceVideoInput $video, array $queryTokens, string $queryKey, array $negativeTokens, string $expectedLanguage, string $requestedFormat): array
    {
        $title = $this->normalizer->normalize($video->title);
        $semantic = $this->normalizer->normalize(implode(' ', $video->semanticLabels));
        $category = $this->normalizer->normalize($video->categoryName ?? '');
        $topic = $this->normalizer->normalize(implode(' ', $video->topicLabels));
        $queryCount = count($queryTokens);
        $titleMatches = count(array_intersect($queryTokens, $title->tokens));
        $semanticMatches = count(array_intersect($queryTokens, $semantic->tokens));
        $categoryMatches = count(array_intersect($queryTokens, $category->tokens));
        $topicMatches = count(array_intersect($queryTokens, $topic->tokens));
        $titleCoverage = $queryCount > 0 ? $titleMatches / $queryCount : 0.0;
        $supportCoverage = $queryCount > 0 ? min(1, ($semanticMatches + $categoryMatches + $topicMatches) / $queryCount) : 0.0;
        $exactPhrase = $queryKey !== '' && str_contains($title->key, $queryKey);
        $negativeMatches = array_values(array_intersect($negativeTokens, $title->tokens));
        $languageMatch = $expectedLanguage === '' || in_array($expectedLanguage, $title->languages, true);
        $formatClass = $video->isShort === null ? 'unknown' : ($video->isShort ? 'shorts' : 'long_form');
        $formatMatch = in_array($requestedFormat, ['', 'any', 'mixed'], true)
            ? true
            : ($formatClass === 'unknown' ? null : $requestedFormat === $formatClass);

        $score = ($titleCoverage * 70) + ($supportCoverage * 15) + ($exactPhrase ? 10 : 0) + ($languageMatch ? 5 : 0);
        $score -= count($negativeMatches) * 35;
        if ($formatMatch === false) {
            $score -= 25;
        }
        $score = $this->statistics->clamp($score);

        $class = match (true) {
            $queryCount === 0 => 'weakly_related',
            $negativeMatches !== [] || ($formatMatch === false && $titleCoverage < 1) => 'off_topic',
            $score >= 75 && $titleCoverage >= 0.75 => 'strictly_relevant',
            $score >= 35 && ($titleCoverage >= 0.5 || $supportCoverage >= 0.5) => 'related',
            $score >= 25 && ($titleMatches + $semanticMatches + $categoryMatches + $topicMatches) > 0 => 'weakly_related',
            default => 'off_topic',
        };

        return [
            'video_id' => $video->videoId,
            'channel_id' => $video->channelId,
            'video_snapshot_id' => $video->videoSnapshotId,
            'channel_snapshot_id' => $video->channelSnapshotId,
            'provider_video_id' => $video->providerVideoId,
            'result_rank' => $video->resultRank,
            'relevance_class' => $class,
            'relevance_score' => round($score, 4),
            'format_class' => $formatClass,
            'views_per_day' => $video->viewsPerDay,
            'view_count' => $video->viewCount,
            'signals' => [
                'query_tokens' => $queryTokens,
                'title_tokens' => $title->tokens,
                'title_coverage' => round($titleCoverage, 4),
                'exact_title_phrase' => $exactPhrase,
                'semantic_matches' => $semanticMatches,
                'category_matches' => $categoryMatches,
                'topic_matches' => $topicMatches,
                'negative_matches' => $negativeMatches,
                'detected_languages' => $title->languages,
                'language_match' => $languageMatch,
                'requested_format' => $requestedFormat,
                'format_match' => $formatMatch,
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function sample(array $rows): array
    {
        $values = $this->metricValues($rows);

        return [
            'count' => count($rows),
            'metric_count' => count($values),
            'state' => count($values) >= self::MIN_ROBUST_SAMPLE ? 'available' : 'insufficient',
            'median_views_per_day' => $this->rounded($this->statistics->median($values)),
            'p25_views_per_day' => $this->rounded($this->statistics->percentile($values, 25)),
            'p75_views_per_day' => $this->rounded($this->statistics->percentile($values, 75)),
            'p90_views_per_day' => $this->rounded($this->statistics->percentile($values, 90)),
            'trimmed_mean_views_per_day' => count($values) >= self::MIN_TRIMMED_SAMPLE ? $this->rounded($this->trimmedMean($values)) : null,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function outliers(array $rows): array
    {
        $values = $this->metricValues($rows);
        rsort($values, SORT_NUMERIC);
        if (count($values) < self::MIN_ROBUST_SAMPLE) {
            return ['state' => 'insufficient', 'metric_count' => count($values), 'dependency' => null, 'removals' => []];
        }

        $total = array_sum($values);
        $topShare = $total > 0 ? $values[0] / $total : null;
        $dependency = match (true) {
            $topShare === null => null,
            $topShare >= 0.50 => 'high',
            $topShare >= 0.30 => 'medium',
            default => 'low',
        };
        $removals = [];
        foreach ([0, 1, 2, 3] as $removed) {
            $remaining = array_slice($values, $removed);
            if (count($remaining) < 2) {
                break;
            }
            $removals[] = [
                'removed_top_count' => $removed,
                'remaining_count' => count($remaining),
                'median_views_per_day' => $this->rounded($this->statistics->median($remaining)),
                'mean_views_per_day' => $this->rounded(array_sum($remaining) / count($remaining)),
            ];
        }

        return [
            'state' => 'available',
            'metric_count' => count($values),
            'top_video_share' => $this->rounded($topShare),
            'dependency' => $dependency,
            'removals' => $removals,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<float>
     */
    private function metricValues(array $rows): array
    {
        $values = [];
        foreach ($rows as $row) {
            if (is_numeric($row['views_per_day']) && (float) $row['views_per_day'] >= 0) {
                $values[] = (float) $row['views_per_day'];
            }
        }

        return $values;
    }

    /** @param list<float> $values */
    private function trimmedMean(array $values): ?float
    {
        if (count($values) < self::MIN_TRIMMED_SAMPLE) {
            return null;
        }
        sort($values, SORT_NUMERIC);
        $trim = max(1, (int) floor(count($values) * 0.10));
        $remaining = array_slice($values, $trim, count($values) - ($trim * 2));

        return $remaining === [] ? null : array_sum($remaining) / count($remaining);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, int>
     */
    private function classCounts(array $rows): array
    {
        $counts = array_fill_keys(['strictly_relevant', 'related', 'weakly_related', 'off_topic'], 0);
        foreach ($rows as $row) {
            $counts[$row['relevance_class']]++;
        }

        return $counts;
    }

    /**
     * @param  list<float>  $left
     * @param  list<float>  $right
     */
    private function spearman(array $left, array $right): ?float
    {
        $count = count($left);
        if ($count < self::MIN_STABILITY_OVERLAP || $count !== count($right)) {
            return null;
        }
        $sum = 0.0;
        foreach ($left as $index => $value) {
            $sum += ($value - $right[$index]) ** 2;
        }

        return 1 - ((6 * $sum) / ($count * (($count ** 2) - 1)));
    }

    /** @return array<string, mixed> */
    private function unavailableStability(string $reason): array
    {
        return [
            'state' => 'unavailable', 'label' => null, 'reason' => $reason,
            'previous_overlap_count' => 0, 'metric_pair_count' => 0,
            'result_overlap' => null, 'channel_overlap' => null, 'order_stability' => null,
            'metric_variance' => null, 'median_variation' => null,
        ];
    }

    private function rounded(?float $value): ?float
    {
        return $value === null ? null : round($value, 4);
    }
}
