<?php

namespace App\Domain\Discovery\Services;

use App\Domain\Discovery\Contracts\ClusteringProvider;
use App\Domain\Discovery\Contracts\TopicExpansionProvider;
use App\Domain\Discovery\Data\DiscoveryCandidateDraft;
use App\Domain\Discovery\Data\DiscoveryCluster;
use App\Domain\Discovery\Data\DiscoveryObservation;
use App\Domain\Discovery\Enums\CandidateEvidenceState;
use Carbon\CarbonImmutable;

class DeterministicDiscoveryEngine
{
    public const LEGACY_FORMULA_VERSION = 'discovery-breakout-v1';

    public const FORMULA_VERSION = 'candidate-evidence-v2';

    public const THRESHOLDS = [
        'minimum_videos' => 3,
        'minimum_channels' => 2,
        'minimum_confidence' => 50,
        'minimum_semantic_coherence' => 55,
        'minimum_seed_relevance' => 20,
        'minimum_typical_performance' => 40,
        'minimum_phrase_quality' => 60,
        'maximum_top_video_share' => 0.70,
        'small_channel_subscribers_max' => 100000,
    ];

    public function __construct(
        private readonly BreakoutDetector $breakoutDetector,
        private readonly TopicExpansionProvider $topicExpansion,
        private readonly ClusteringProvider $clustering,
        private readonly MultilingualPhraseNormalizer $normalizer,
    ) {}

    /**
     * @param  list<DiscoveryObservation>  $observations
     * @param  array<string, int|float>  $thresholds
     * @return list<DiscoveryCandidateDraft>
     */
    public function generate(
        array $observations,
        int $candidateLimit = 20,
        string $formulaVersion = self::FORMULA_VERSION,
        array $thresholds = self::THRESHOLDS,
        ?CarbonImmutable $referenceTime = null,
    ): array {
        $candidateLimit = max(1, min($candidateLimit, 20));
        $clusters = $this->clustering->cluster($this->topicExpansion->expand(
            $this->breakoutDetector->detect($observations),
        ));

        if ($formulaVersion === self::LEGACY_FORMULA_VERSION) {
            return $this->legacyDrafts($clusters, $observations, $candidateLimit);
        }

        $referenceTime ??= collect($observations)->max('publishedAt') ?? CarbonImmutable::parse('1970-01-01 UTC');
        $drafts = array_map(
            fn (DiscoveryCluster $cluster): DiscoveryCandidateDraft => $this->evidenceDraft($cluster, $observations, $thresholds, $referenceTime),
            $clusters,
        );

        usort($drafts, fn (DiscoveryCandidateDraft $left, DiscoveryCandidateDraft $right): int => [
            $left->evidenceState === CandidateEvidenceState::Candidate ? 0 : 1,
            -$left->overallScore,
            -$left->confidenceScore,
            $left->phrase,
        ] <=> [
            $right->evidenceState === CandidateEvidenceState::Candidate ? 0 : 1,
            -$right->overallScore,
            -$right->confidenceScore,
            $right->phrase,
        ]);

        return array_slice($drafts, 0, $candidateLimit);
    }

    /**
     * @param  list<DiscoveryObservation>  $observations
     * @param  array<string, int|float>  $thresholds
     */
    private function evidenceDraft(DiscoveryCluster $cluster, array $observations, array $thresholds, CarbonImmutable $referenceTime): DiscoveryCandidateDraft
    {
        $members = collect($observations)->whereIn('providerVideoId', $cluster->videoIds)->unique('providerVideoId')->values();
        $videoCount = $members->count();
        $channelCount = $members->pluck('providerChannelId')->unique()->count();
        $seedCount = count($cluster->seedQueries);
        $allSeedCount = max(collect($observations)->pluck('seedQuery')->unique()->count(), 1);
        $velocities = $members->pluck('viewsPerDay')->filter(fn (mixed $value): bool => $value !== null)->map(fn (mixed $value): float => (float) $value)->values()->all();
        $allVelocities = collect($observations)->pluck('viewsPerDay')->filter(fn (mixed $value): bool => $value !== null)->map(fn (mixed $value): float => (float) $value)->values()->all();
        $baseline = $this->median($allVelocities);
        $typicalMedian = $this->median($velocities);
        $withoutTop = $velocities;
        rsort($withoutTop, SORT_NUMERIC);
        array_shift($withoutTop);
        $withoutTopMedian = $this->median($withoutTop);
        $topShare = array_sum($velocities) > 0 ? max($velocities ?: [0]) / array_sum($velocities) : null;
        $outlierDependent = count($velocities) < 3
            || $topShare === null
            || $topShare > (float) $thresholds['maximum_top_video_share']
            || $withoutTopMedian <= 0;
        $phraseTokens = array_values(array_filter(explode(' ', $cluster->representativePhrase)));
        $titleCoherence = $members->map(function (DiscoveryObservation $observation) use ($phraseTokens): float {
            return $this->coverage($phraseTokens, $this->normalizer->normalize($observation->title)->tokens) * 100;
        })->all();
        $seedRelevance = collect($cluster->seedQueries)->map(function (string $seed) use ($phraseTokens): float {
            $seedTokens = $this->normalizer->normalize($seed)->tokens;

            return max($this->coverage($phraseTokens, $seedTokens), $this->coverage($seedTokens, $phraseTokens)) * 100;
        })->max() ?? 0.0;
        $smallChannelProof = $members->filter(fn (DiscoveryObservation $observation): bool => $observation->subscriberCount !== null
            && $observation->subscriberCount <= (int) $thresholds['small_channel_subscribers_max']
            && ($observation->reachRatio ?? 0) >= 1.5
        )->pluck('providerChannelId')->unique()->count();
        $ages = $members->map(fn (DiscoveryObservation $observation): float => (float) max(0, $observation->publishedAt->diffInDays($referenceTime)))->all();
        $stability = count($velocities) >= 3 && $typicalMedian > 0
            ? max(0, min(100, 100 * (1 - ($this->median(array_map(fn (float $value): float => abs($value - $typicalMedian), $velocities)) / $typicalMedian))))
            : null;

        $meaningfulPhraseTokens = array_diff($phraseTokens, ['general', 'topic', 'idea', 'thing', 'basic']);
        $phraseQuality = count($phraseTokens) >= 2 && count($phraseTokens) <= 4 && $meaningfulPhraseTokens !== [] ? 100 : 25;
        $components = [
            'frequency' => min(100, ($videoCount / 5) * 100),
            'unique_channels' => min(100, ($channelCount / 4) * 100),
            'seed_coverage' => min(100, ($seedCount / $allSeedCount) * 100),
            'semantic_coherence' => $this->median($titleCoherence),
            'typical_performance' => $baseline > 0 ? min(100, ($typicalMedian / ($baseline * 2)) * 100) : 0,
            'outlier_resistance' => $baseline > 0 && $withoutTop !== [] ? min(100, ($withoutTopMedian / ($baseline * 2)) * 100) : 0,
            'small_channel_proof' => min(100, $smallChannelProof * 50),
            'freshness' => $ages === [] ? 0 : max(0, 100 - ($this->median($ages) / 1.8)),
            'stability' => $stability ?? 0,
            'seed_relevance' => min(100, $seedRelevance),
            'phrase_quality' => $phraseQuality,
        ];
        $weights = [
            'frequency' => 0.12, 'unique_channels' => 0.12, 'seed_coverage' => 0.08,
            'semantic_coherence' => 0.10, 'typical_performance' => 0.12, 'outlier_resistance' => 0.12,
            'small_channel_proof' => 0.10, 'freshness' => 0.08, 'stability' => 0.08,
            'seed_relevance' => 0.05, 'phrase_quality' => 0.03,
        ];
        $score = array_sum(array_map(fn (string $key): float => $components[$key] * $weights[$key], array_keys($weights)));
        $score = min($score, $videoCount === 1 ? 35 : ($videoCount < 3 || $channelCount < 2 ? 59 : 100));
        $metricCoverage = count($velocities) / max($videoCount, 1);
        $subscriberCoverage = $members->whereNotNull('subscriberCount')->count() / max($videoCount, 1);
        $confidence = min(100, ($videoCount * 10) + ($channelCount * 8) + ($seedCount * 5) + ($metricCoverage * 25) + ($subscriberCoverage * 10));
        $confidence = min($confidence, $videoCount === 1 ? 35 : ($videoCount < 3 ? 49 : 100));

        $reasons = [];
        $this->requireThreshold($reasons, $videoCount, (float) $thresholds['minimum_videos'], 'At least three supporting videos are required.');
        $this->requireThreshold($reasons, $channelCount, (float) $thresholds['minimum_channels'], 'Evidence must span at least two unique channels.');
        $this->requireThreshold($reasons, $components['semantic_coherence'], (float) $thresholds['minimum_semantic_coherence'], 'The phrase is not coherent across enough titles.');
        $this->requireThreshold($reasons, $components['seed_relevance'], (float) $thresholds['minimum_seed_relevance'], 'The phrase has weak relevance to its seed queries.');
        $this->requireThreshold($reasons, $components['typical_performance'], (float) $thresholds['minimum_typical_performance'], 'Typical performance is below the frozen threshold.');
        $this->requireThreshold($reasons, $components['phrase_quality'], (float) $thresholds['minimum_phrase_quality'], 'The normalized phrase is incomplete or too broad.');
        $this->requireThreshold($reasons, $confidence, (float) $thresholds['minimum_confidence'], 'Calculated confidence is below the frozen threshold.');
        if ($outlierDependent) {
            $reasons[] = 'Performance depends too heavily on one video or lacks enough outlier-free evidence.';
        }

        $state = $reasons === [] ? CandidateEvidenceState::Candidate : CandidateEvidenceState::WeakPhraseSignal;
        $analyzerRunIds = $members->pluck('analyzerRunPublicId')->filter()->unique()->values()->all();
        $inferredTopics = $members->flatMap(fn (DiscoveryObservation $observation): array => $observation->inferredTopics)->countBy()->sortDesc()->keys()->take(8)->values()->all();

        return new DiscoveryCandidateDraft(
            phrase: $cluster->representativePhrase,
            clusterKey: $cluster->clusterKey,
            summary: $state === CandidateEvidenceState::Candidate
                ? "Candidate niche supported by {$videoCount} videos across {$channelCount} channels."
                : "Weak phrase signal from {$videoCount} video".($videoCount === 1 ? '' : 's')." across {$channelCount} channel".($channelCount === 1 ? '' : 's').'.',
            evidence: [
                'evidence_state' => $state->value,
                'quality_label' => $state === CandidateEvidenceState::Candidate ? 'Candidate niche' : 'Weak phrase signal',
                'suggested_validation_query' => $cluster->representativePhrase,
                'insufficiency_reasons' => $reasons,
                'video_ids' => $cluster->videoIds,
                'channel_ids' => $members->pluck('providerChannelId')->unique()->values()->all(),
                'seed_queries' => $cluster->seedQueries,
                'original_phrases' => $cluster->memberPhrases,
                'normalized_phrase' => $cluster->representativePhrase,
                'normalization_version' => MultilingualPhraseNormalizer::VERSION,
                'detected_languages' => $cluster->languages,
                'normalization_transformations' => $cluster->normalizationTransformations,
                'source_video_count' => $videoCount,
                'unique_channel_count' => $channelCount,
                'seed_count' => $seedCount,
                'small_channel_proof_count' => $smallChannelProof,
                'typical_median_views_per_day' => $velocities === [] ? null : round($typicalMedian, 4),
                'outlier_free_median_views_per_day' => $withoutTop === [] ? null : round($withoutTopMedian, 4),
                'top_video_performance_share' => $topShare === null ? null : round($topShare, 4),
                'outlier_dependent' => $outlierDependent,
                'stability_score' => $stability === null ? null : round($stability, 4),
                'components' => array_map(fn (float $value): float => round($value, 4), $components),
                'thresholds' => $thresholds,
                'input_reference_time' => $referenceTime->toIso8601String(),
                'analyzer_run_ids' => $analyzerRunIds,
                'evidence_provenance' => $analyzerRunIds === [] ? ['research_snapshot'] : ['research_snapshot', 'analyzer_profile'],
                'opportunity_score_status' => 'requires_validation_search',
                'inferred_topics' => $inferredTopics,
                'inferred_topic_provenance' => $inferredTopics === [] ? null : 'semantic-title-terms-v1',
            ],
            overallScore: round($score, 4),
            confidenceScore: round($confidence, 4),
            formulaVersion: self::FORMULA_VERSION,
            evidenceState: $state,
        );
    }

    /** @param list<string> $reasons */
    private function requireThreshold(array &$reasons, float|int $actual, float $minimum, string $reason): void
    {
        if ($actual < $minimum) {
            $reasons[] = $reason;
        }
    }

    /**
     * @param  list<string>  $needles
     * @param  list<string>  $haystack
     */
    private function coverage(array $needles, array $haystack): float
    {
        return count($needles) === 0 ? 0 : count(array_intersect($needles, $haystack)) / count($needles);
    }

    /** @param array<int, float> $values */
    private function median(array $values): float
    {
        if ($values === []) {
            return 0.0;
        }

        sort($values, SORT_NUMERIC);
        $middle = intdiv(count($values), 2);

        return count($values) % 2 === 0 ? ($values[$middle - 1] + $values[$middle]) / 2 : $values[$middle];
    }

    /**
     * @param  list<DiscoveryCluster>  $clusters
     * @param  list<DiscoveryObservation>  $observations
     * @return list<DiscoveryCandidateDraft>
     */
    private function legacyDrafts(array $clusters, array $observations, int $candidateLimit): array
    {
        $drafts = [];
        foreach (array_slice($clusters, 0, $candidateLimit) as $cluster) {
            $videoCount = count($cluster->videoIds);
            $seedCount = count($cluster->seedQueries);
            $members = collect($observations)->whereIn('providerVideoId', $cluster->videoIds);
            $analyzerRunIds = $members->pluck('analyzerRunPublicId')->filter()->unique()->values()->all();
            $inferredTopics = $members->flatMap(fn (DiscoveryObservation $observation): array => $observation->inferredTopics)->countBy()->sortDesc()->keys()->take(8)->values()->all();
            $drafts[] = new DiscoveryCandidateDraft(
                phrase: $cluster->representativePhrase,
                clusterKey: $cluster->clusterKey,
                summary: "Observed across {$videoCount} breakout video".($videoCount === 1 ? '' : 's')." from {$seedCount} seed".($seedCount === 1 ? '' : 's').'.',
                evidence: [
                    'observed_signal' => 'returned_video_breakout', 'video_ids' => $cluster->videoIds,
                    'seed_queries' => $cluster->seedQueries, 'member_phrases' => $cluster->memberPhrases,
                    'source_video_count' => $videoCount, 'seed_count' => $seedCount,
                    'analyzer_run_ids' => $analyzerRunIds,
                    'evidence_provenance' => $analyzerRunIds === [] ? ['research_snapshot'] : ['research_snapshot', 'analyzer_profile'],
                    'opportunity_score_status' => 'requires_validation_search', 'inferred_topics' => $inferredTopics,
                    'inferred_topic_provenance' => $inferredTopics === [] ? null : 'semantic-title-terms-v1',
                ],
                overallScore: round(min($cluster->strength / max($videoCount, 1), 100), 4),
                confidenceScore: round(min(25 + ($videoCount * 15) + ($seedCount * 10), 100), 4),
                formulaVersion: self::LEGACY_FORMULA_VERSION,
            );
        }

        return $drafts;
    }
}
