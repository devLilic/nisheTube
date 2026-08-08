<?php

namespace App\Domain\Scoring\Services;

use App\Domain\Scoring\Data\OpportunityScoreResult;
use App\Domain\Scoring\Data\ScoringInput;
use App\Domain\Scoring\Data\ScoringVideoInput;
use UnexpectedValueException;

final class NicheOpportunityV1
{
    public const VERSION = 'niche-opportunity-v1';

    public function __construct(private readonly RobustStatistics $statistics) {}

    public function calculate(ScoringInput $input): OpportunityScoreResult
    {
        $signals = $this->signals($input);
        $demand = $this->demandMomentum($signals);
        $competition = $this->competitionOpportunity($signals);
        $reachability = $this->audienceReachability($signals);
        $freshness = $this->contentFreshnessGap($signals, $demand);
        $viability = $this->creatorViability($signals, $demand);
        $confidence = $this->confidence($input, $signals);
        $overall = $this->weighted([
            'demand_momentum' => $demand,
            'competition_opportunity' => $competition,
            'audience_reachability' => $reachability,
            'content_freshness_gap' => $freshness,
            'creator_viability' => $viability,
        ], 'weights');

        return new OpportunityScoreResult(
            formulaVersion: self::VERSION,
            overallScore: $this->score($overall),
            demandMomentumScore: $this->score($demand),
            competitionOpportunityScore: $this->score($competition),
            audienceReachabilityScore: $this->score($reachability),
            contentFreshnessGapScore: $this->score($freshness),
            creatorViabilityScore: $this->score($viability),
            confidenceScore: $this->score($confidence),
            sampleSize: $signals['sample_size'],
            inputSummary: $this->inputSummary($input, $signals),
            explanations: $this->explanations($signals, $demand),
            warnings: $this->warnings($input, $signals),
        );
    }

    /**
     * @return array{
     *   sample_size: int, unique_channels: int, enrichment_ratio: float,
     *   view_velocity_availability: float, subscriber_availability: float,
     *   engagement_availability: float, format_availability: float, age_availability: float,
     *   median_views_per_day: float, upper_quartile_views_per_day: float,
     *   winsorized_mean_views_per_day: float, meaningful_velocity_share: float,
     *   recency_weighted_velocity: float, previous_median_views_per_day: float|null,
     *   velocity_change_ratio: float|null, top_three_channel_view_share: float,
     *   large_channel_result_share: float, distinct_channel_ratio: float,
     *   largest_channel_result_share: float, median_reach_ratio: float,
     *   small_mid_top_performer_share: float, breakout_rate: float,
     *   top_performer_channel_diversity: float, top_median_age_days: float,
     *   recent_top_performer_share: float, old_top_performer_share: float,
     *   repeat_success_channel_share: float, performance_stability: float,
     *   angle_diversity: float, cadence_score: float, short_count: int,
     *   long_form_count: int, known_category_count: int, cadence_availability: float
     * }
     */
    private function signals(ScoringInput $input): array
    {
        $sampleSize = count($input->videos);
        $channelIds = [];
        $viewsPerDay = [];
        $ages = [];
        $reachRatios = [];
        $subscriberByChannel = [];
        $channelResultCounts = [];
        $channelViews = [];
        $engagementCount = 0;
        $formatCount = 0;
        $shortCount = 0;
        $longFormCount = 0;
        $categories = [];
        $cadencesByChannel = [];

        foreach ($input->videos as $video) {
            $channelIds[$video->channelId] = true;
            $channelResultCounts[$video->channelId] = ($channelResultCounts[$video->channelId] ?? 0) + 1;

            if ($video->viewsPerDay !== null) {
                $viewsPerDay[] = $video->viewsPerDay;
            }

            if ($video->ageDays !== null) {
                $ages[] = $video->ageDays;
            }

            if ($video->viewsToSubscribersRatio !== null) {
                $reachRatios[] = $video->viewsToSubscribersRatio;
            }

            if ($video->subscriberCount !== null && ! $video->subscriberCountHidden) {
                $subscriberByChannel[$video->channelId] = $video->subscriberCount;
            }

            if ($video->viewCount !== null) {
                $channelViews[$video->channelId] = ($channelViews[$video->channelId] ?? 0) + $video->viewCount;
            }

            if ($video->likeCount !== null && $video->commentCount !== null) {
                $engagementCount++;
            }

            if ($video->isShort !== null) {
                $formatCount++;
                $video->isShort ? $shortCount++ : $longFormCount++;
            }

            if ($video->categoryId !== null) {
                $categories[$video->categoryId] = true;
            }

            if ($video->lifetimeUploadsPerMonth !== null) {
                $cadencesByChannel[$video->channelId] = $video->lifetimeUploadsPerMonth;
            }
        }

        $medianVpd = $this->statistics->median($viewsPerDay) ?? 0.0;
        $upperVpd = $this->statistics->percentile($viewsPerDay, 75) ?? 0.0;
        $topVideos = array_values(array_filter(
            $input->videos,
            static fn (ScoringVideoInput $video): bool => $video->viewsPerDay !== null
                && $video->viewsPerDay >= $upperVpd,
        ));
        $recentHalfLife = $this->number('normalization.recency_half_life_days');
        $weightedVelocities = [];

        foreach ($input->videos as $video) {
            if ($video->viewsPerDay === null || $video->ageDays === null) {
                continue;
            }

            $weightedVelocities[] = $video->viewsPerDay * (2 ** (-$video->ageDays / $recentHalfLife));
        }

        $subscriberValues = array_values($subscriberByChannel);
        $subscriberPeerUpper = $this->statistics->percentile($subscriberValues, 75) ?? 0.0;
        $topWithSubscribers = array_values(array_filter(
            $topVideos,
            static fn (ScoringVideoInput $video): bool => $video->subscriberCount !== null
                && ! $video->subscriberCountHidden,
        ));
        $smallMidTopCount = count(array_filter(
            $topWithSubscribers,
            static fn (ScoringVideoInput $video): bool => $video->subscriberCount !== null
                && $video->subscriberCount <= $subscriberPeerUpper,
        ));
        $ratioVideos = array_values(array_filter(
            $input->videos,
            static fn (ScoringVideoInput $video): bool => $video->viewsToSubscribersRatio !== null,
        ));
        $breakouts = count(array_filter(
            $ratioVideos,
            static fn (ScoringVideoInput $video): bool => $video->viewsPerDay !== null
                && $video->viewsPerDay >= $upperVpd
                && $video->viewsToSubscribersRatio !== null
                && $video->viewsToSubscribersRatio >= 1.0,
        ));
        $topChannelIds = [];
        $topAges = [];

        foreach ($topVideos as $video) {
            $topChannelIds[$video->channelId] = true;

            if ($video->ageDays !== null) {
                $topAges[] = $video->ageDays;
            }
        }

        $successfulByChannel = [];

        foreach ($input->videos as $video) {
            if ($video->viewsPerDay !== null && $video->viewsPerDay >= $medianVpd) {
                $successfulByChannel[$video->channelId] = ($successfulByChannel[$video->channelId] ?? 0) + 1;
            }
        }

        $repeatChannels = count(array_filter(
            $successfulByChannel,
            static fn (int $count): bool => $count >= 2,
        ));
        $trimmedValues = $viewsPerDay;
        sort($trimmedValues, SORT_NUMERIC);

        if (count($trimmedValues) >= 3) {
            array_pop($trimmedValues);
        }

        $trimmedMean = $trimmedValues !== [] ? array_sum($trimmedValues) / count($trimmedValues) : 0.0;
        $totalChannelViews = array_sum($channelViews);
        rsort($channelViews, SORT_NUMERIC);
        $topThreeViews = array_sum(array_slice($channelViews, 0, 3));
        $largeThreshold = $this->number('normalization.large_channel_subscribers');
        $subscriberKnownVideos = array_values(array_filter(
            $input->videos,
            static fn (ScoringVideoInput $video): bool => $video->subscriberCount !== null
                && ! $video->subscriberCountHidden,
        ));
        $largeChannelVideos = count(array_filter(
            $subscriberKnownVideos,
            static fn (ScoringVideoInput $video): bool => $video->subscriberCount !== null
                && $video->subscriberCount >= $largeThreshold,
        ));
        $recentDays = $this->number('normalization.recent_days');
        $staleDays = $this->number('normalization.stale_days');
        $recentTop = count(array_filter($topAges, static fn (float $age): bool => $age <= $recentDays));
        $oldTop = count(array_filter($topAges, static fn (float $age): bool => $age >= $staleDays));
        $cadences = array_values($cadencesByChannel);

        return [
            'sample_size' => $sampleSize,
            'unique_channels' => count($channelIds),
            'enrichment_ratio' => $this->ratio($input->enrichedResultCount, max(1, $input->collectedResultCount)),
            'view_velocity_availability' => $this->ratio(count($viewsPerDay), $sampleSize),
            'subscriber_availability' => $this->ratio(count($subscriberByChannel), count($channelIds)),
            'engagement_availability' => $this->ratio($engagementCount, $sampleSize),
            'format_availability' => $this->ratio($formatCount, $sampleSize),
            'age_availability' => $this->ratio(count($ages), $sampleSize),
            'median_views_per_day' => $medianVpd,
            'upper_quartile_views_per_day' => $upperVpd,
            'winsorized_mean_views_per_day' => $this->statistics->winsorizedMean(
                $viewsPerDay,
                $this->number('normalization.winsor_lower_percentile'),
                $this->number('normalization.winsor_upper_percentile'),
            ) ?? 0.0,
            'meaningful_velocity_share' => $this->ratio(count(array_filter(
                $viewsPerDay,
                fn (float $value): bool => $value >= $this->number('normalization.meaningful_views_per_day'),
            )), count($viewsPerDay)),
            'recency_weighted_velocity' => $this->statistics->winsorizedMean(
                $weightedVelocities,
                $this->number('normalization.winsor_lower_percentile'),
                $this->number('normalization.winsor_upper_percentile'),
            ) ?? 0.0,
            'previous_median_views_per_day' => $input->previousMedianViewsPerDay,
            'velocity_change_ratio' => $input->previousMedianViewsPerDay !== null
                && $input->previousMedianViewsPerDay > 0
                    ? ($medianVpd - $input->previousMedianViewsPerDay) / $input->previousMedianViewsPerDay
                    : null,
            'top_three_channel_view_share' => $totalChannelViews > 0 ? $topThreeViews / $totalChannelViews : 1.0,
            'large_channel_result_share' => $this->ratio($largeChannelVideos, count($subscriberKnownVideos)),
            'distinct_channel_ratio' => $this->ratio(count($channelIds), $sampleSize),
            'largest_channel_result_share' => $this->ratio(
                $channelResultCounts !== [] ? max($channelResultCounts) : 0,
                $sampleSize,
            ),
            'median_reach_ratio' => $this->statistics->median($reachRatios) ?? 0.0,
            'small_mid_top_performer_share' => $this->ratio($smallMidTopCount, count($topWithSubscribers)),
            'breakout_rate' => $this->ratio($breakouts, count($ratioVideos)),
            'top_performer_channel_diversity' => $this->ratio(count($topChannelIds), count($topVideos)),
            'top_median_age_days' => $this->statistics->median($topAges) ?? 0.0,
            'recent_top_performer_share' => $this->ratio($recentTop, count($topAges)),
            'old_top_performer_share' => $this->ratio($oldTop, count($topAges)),
            'repeat_success_channel_share' => $this->ratio($repeatChannels, count($successfulByChannel)),
            'performance_stability' => $trimmedMean > 0
                ? $this->statistics->clamp($medianVpd / $trimmedMean, 0, 1)
                : 0.0,
            'angle_diversity' => $this->angleDiversity(count($categories), $shortCount, $longFormCount),
            'cadence_score' => $this->cadenceScore($this->statistics->median($cadences)),
            'short_count' => $shortCount,
            'long_form_count' => $longFormCount,
            'known_category_count' => count($categories),
            'cadence_availability' => $this->ratio(count($cadencesByChannel), count($channelIds)),
        ];
    }

    /** @param array<string, float|int|null> $signals */
    private function demandMomentum(array $signals): float
    {
        $scores = [
            'median' => $this->statistics->logarithmicScore(
                (float) $signals['median_views_per_day'],
                $this->number('normalization.median_views_per_day_low'),
                $this->number('normalization.median_views_per_day_high'),
            ),
            'upper_quartile' => $this->statistics->logarithmicScore(
                (float) $signals['upper_quartile_views_per_day'],
                $this->number('normalization.upper_quartile_views_per_day_low'),
                $this->number('normalization.upper_quartile_views_per_day_high'),
            ),
            'meaningful_share' => $this->statistics->linearScore((float) $signals['meaningful_velocity_share'], 0.15, 0.75),
            'recency_weighted' => $this->statistics->logarithmicScore(
                (float) $signals['recency_weighted_velocity'],
                $this->number('normalization.median_views_per_day_low'),
                $this->number('normalization.median_views_per_day_high'),
            ),
        ];
        $weights = ['median' => 0.30, 'upper_quartile' => 0.25, 'meaningful_share' => 0.25, 'recency_weighted' => 0.20];

        if ($signals['velocity_change_ratio'] !== null) {
            $scores['history'] = $this->statistics->linearScore((float) $signals['velocity_change_ratio'], -0.50, 1.00);
            $weights = ['median' => 0.24, 'upper_quartile' => 0.20, 'meaningful_share' => 0.20, 'recency_weighted' => 0.16, 'history' => 0.20];
        }

        return $this->weightedValues($scores, $weights);
    }

    /** @param array<string, float|int|null> $signals */
    private function competitionOpportunity(array $signals): float
    {
        return $this->weightedValues([
            'concentration' => $this->statistics->inverseLinearScore((float) $signals['top_three_channel_view_share'], 0.35, 0.85),
            'large_channels' => $this->statistics->inverseLinearScore((float) $signals['large_channel_result_share'], 0.10, 0.65),
            'distinct_channels' => $this->statistics->linearScore((float) $signals['distinct_channel_ratio'], 0.35, 0.90),
            'duplicate_dominance' => $this->statistics->inverseLinearScore((float) $signals['largest_channel_result_share'], 0.15, 0.50),
        ], [
            'concentration' => 0.35,
            'large_channels' => 0.25,
            'distinct_channels' => 0.25,
            'duplicate_dominance' => 0.15,
        ]);
    }

    /** @param array<string, float|int|null> $signals */
    private function audienceReachability(array $signals): float
    {
        return $this->weightedValues([
            'reach_ratio' => $this->statistics->linearScore(
                (float) $signals['median_reach_ratio'],
                $this->number('normalization.reach_ratio_low'),
                $this->number('normalization.reach_ratio_high'),
            ),
            'small_mid_top_share' => $this->statistics->linearScore((float) $signals['small_mid_top_performer_share'], 0.20, 0.80),
            'breakout_rate' => $this->statistics->linearScore((float) $signals['breakout_rate'], 0.05, 0.30),
            'top_diversity' => $this->statistics->linearScore((float) $signals['top_performer_channel_diversity'], 0.40, 1.00),
        ], [
            'reach_ratio' => 0.35,
            'small_mid_top_share' => 0.25,
            'breakout_rate' => 0.25,
            'top_diversity' => 0.15,
        ]);
    }

    /** @param array<string, float|int|null> $signals */
    private function contentFreshnessGap(array $signals, float $demand): float
    {
        $gapEvidence = $this->weightedValues([
            'winner_age' => $this->statistics->linearScore(
                (float) $signals['top_median_age_days'],
                $this->number('normalization.recent_days'),
                $this->number('normalization.freshness_gap_max_days'),
            ),
            'recent_scarcity' => $this->statistics->inverseLinearScore((float) $signals['recent_top_performer_share'], 0.10, 0.80),
            'old_winners' => $this->statistics->linearScore((float) $signals['old_top_performer_share'], 0.10, 0.70),
        ], [
            'winner_age' => 0.40,
            'recent_scarcity' => 0.35,
            'old_winners' => 0.25,
        ]);
        $demandEvidence = $this->statistics->linearScore($demand, 15, 60) / 100;

        return $gapEvidence * $demandEvidence;
    }

    /** @param array<string, float|int|null> $signals */
    private function creatorViability(array $signals, float $demand): float
    {
        $evidence = $this->weightedValues([
            'repeat_success' => $this->statistics->linearScore((float) $signals['repeat_success_channel_share'], 0.05, 0.40),
            'stability' => ((float) $signals['performance_stability']) * 100,
            'cadence' => (float) $signals['cadence_score'],
            'angles' => (float) $signals['angle_diversity'],
        ], [
            'repeat_success' => 0.35,
            'stability' => 0.30,
            'cadence' => 0.20,
            'angles' => 0.15,
        ]);
        $demandSupport = 0.35 + (0.65 * ($demand / 100));

        return $evidence * $demandSupport;
    }

    /** @param array<string, float|int|null> $signals */
    private function confidence(ScoringInput $input, array $signals): float
    {
        $score = $this->weightedValues([
            'sample_size' => $this->statistics->linearScore(
                (float) $signals['sample_size'],
                5,
                $this->number('sample.target'),
            ),
            'unique_channels' => $this->statistics->linearScore(
                (float) $signals['unique_channels'],
                3,
                $this->number('sample.channel_target'),
            ),
            'enrichment' => ((float) $signals['enrichment_ratio']) * 100,
            'subscriber_availability' => ((float) $signals['subscriber_availability']) * 100,
            'engagement_availability' => ((float) $signals['engagement_availability']) * 100,
            'metadata_availability' => (((float) $signals['format_availability'] + (float) $signals['age_availability']) / 2) * 100,
            'comparable_history' => $signals['previous_median_views_per_day'] !== null ? 100 : 0,
        ], $this->section('confidence_weights'));

        if ((int) $signals['sample_size'] < (int) $this->number('sample.minimum')) {
            $score *= 0.75;
        }

        if ($input->collectionWarnings !== [] || (float) $signals['enrichment_ratio'] < $this->number('normalization.partial_enrichment_ratio')) {
            $score *= 0.85;
        }

        return $score;
    }

    /**
     * @param  array<string, float|int|null>  $signals
     * @return array<string, mixed>
     */
    private function inputSummary(ScoringInput $input, array $signals): array
    {
        return [
            'configuration' => $this->configuration(),
            'sample' => [
                'requested_result_count' => $input->requestedResultCount,
                'collected_result_count' => $input->collectedResultCount,
                'enriched_result_count' => $input->enrichedResultCount,
                'scored_video_count' => $signals['sample_size'],
                'unique_channel_count' => $signals['unique_channels'],
                'enrichment_completion_percent' => $this->summaryNumber(((float) $signals['enrichment_ratio']) * 100),
            ],
            'availability' => [
                'view_velocity_percent' => $this->summaryNumber(((float) $signals['view_velocity_availability']) * 100),
                'subscriber_percent' => $this->summaryNumber(((float) $signals['subscriber_availability']) * 100),
                'engagement_percent' => $this->summaryNumber(((float) $signals['engagement_availability']) * 100),
                'format_percent' => $this->summaryNumber(((float) $signals['format_availability']) * 100),
                'age_percent' => $this->summaryNumber(((float) $signals['age_availability']) * 100),
                'channel_cadence_percent' => $this->summaryNumber(((float) $signals['cadence_availability']) * 100),
            ],
            'statistics' => array_map(
                fn (float|int|null $value): float|int|null => is_float($value) ? $this->summaryNumber($value) : $value,
                $signals,
            ),
        ];
    }

    /**
     * @param  array<string, float|int|null>  $signals
     * @return array<string, string>
     */
    private function explanations(array $signals, float $demand): array
    {
        return [
            'demand_momentum' => sprintf(
                'Observed median velocity was %s views/day and the upper quartile was %s views/day; this measures returned-video activity, not search volume.',
                number_format((float) $signals['median_views_per_day'], 1),
                number_format((float) $signals['upper_quartile_views_per_day'], 1),
            ),
            'competition_opportunity' => sprintf(
                'The top three sampled channels captured %.1f%% of observed views, while %.1f%% of results came from distinct channels.',
                ((float) $signals['top_three_channel_view_share']) * 100,
                ((float) $signals['distinct_channel_ratio']) * 100,
            ),
            'audience_reachability' => sprintf(
                'The median views-to-subscriber ratio was %.2f and %.1f%% of subscriber-visible top performers came from small or mid-sized sample peers.',
                (float) $signals['median_reach_ratio'],
                ((float) $signals['small_mid_top_performer_share']) * 100,
            ),
            'content_freshness_gap' => sprintf(
                'Top-performing results had a median age of %.1f days; gap evidence was gated by the %.1f demand component so stale low-demand results cannot score highly.',
                (float) $signals['top_median_age_days'],
                $demand,
            ),
            'creator_viability' => sprintf(
                '%.1f%% of successful sampled channels had multiple above-median videos; outlier-resistant stability and stored lifetime cadence were also considered.',
                ((float) $signals['repeat_success_channel_share']) * 100,
            ),
        ];
    }

    /**
     * @param  array<string, float|int|null>  $signals
     * @return list<array{code: string, message: string}>
     */
    private function warnings(ScoringInput $input, array $signals): array
    {
        $warnings = [];
        $availabilityThreshold = $this->number('normalization.availability_warning_ratio');

        if ((int) $signals['sample_size'] < (int) $this->number('sample.minimum')) {
            $warnings[] = $this->warning('insufficient_sample', 'The enriched sample is below the configured minimum; treat this score as exploratory.');
        }

        if ((float) $signals['view_velocity_availability'] < $availabilityThreshold) {
            $warnings[] = $this->warning('missing_view_velocity', 'Some videos have no usable view velocity, reducing demand evidence and confidence.');
        }

        if ((float) $signals['subscriber_availability'] < $availabilityThreshold) {
            $warnings[] = $this->warning('missing_subscriber_counts', 'Hidden or missing subscriber counts reduce reachability evidence and confidence.');
        }

        if ((float) $signals['engagement_availability'] < $availabilityThreshold) {
            $warnings[] = $this->warning('missing_engagement_metrics', 'Some like or comment counts are unavailable and were not treated as zero.');
        }

        $mixedMinimum = (int) $this->number('normalization.mixed_format_minimum_each');

        if ((int) $signals['short_count'] >= $mixedMinimum && (int) $signals['long_form_count'] >= $mixedMinimum) {
            $warnings[] = $this->warning('mixed_content_formats', 'The sample mixes Shorts and long-form videos; interpret combined component scores cautiously.');
        }

        if ((float) $signals['format_availability'] < $availabilityThreshold) {
            $warnings[] = $this->warning('unknown_content_formats', 'Some video formats could not be classified.');
        }

        if ($signals['previous_median_views_per_day'] === null) {
            $warnings[] = $this->warning('no_comparable_history', 'No earlier compatible score was available; demand momentum uses a cross-sectional baseline.');
        }

        if ((float) $signals['cadence_availability'] < $availabilityThreshold) {
            $warnings[] = $this->warning('limited_creator_cadence_data', 'Creator viability uses only stored lifetime publishing evidence where available, not a complete recent upload history.');
        }

        if ($input->collectionWarnings !== [] || (float) $signals['enrichment_ratio'] < $this->number('normalization.partial_enrichment_ratio')) {
            $warnings[] = $this->warning('partial_collection', 'The run contains partial collection or enrichment evidence; confidence was reduced.');
        }

        return $warnings;
    }

    /** @return array{code: string, message: string} */
    private function warning(string $code, string $message): array
    {
        return ['code' => $code, 'message' => $message];
    }

    private function ratio(int $numerator, int $denominator): float
    {
        return $denominator > 0 ? $this->statistics->clamp($numerator / $denominator, 0, 1) : 0.0;
    }

    private function angleDiversity(int $categoryCount, int $shortCount, int $longFormCount): float
    {
        $categoryScore = $this->statistics->linearScore($categoryCount, 1, 4);
        $formatScore = $shortCount > 0 && $longFormCount > 0 ? 100.0 : 45.0;

        return ($categoryScore * 0.70) + ($formatScore * 0.30);
    }

    private function cadenceScore(?float $uploadsPerMonth): float
    {
        if ($uploadsPerMonth === null) {
            return 0.0;
        }

        if ($uploadsPerMonth <= 4) {
            return $this->statistics->linearScore($uploadsPerMonth, 0.25, 4);
        }

        return $this->statistics->inverseLinearScore($uploadsPerMonth, 12, 40);
    }

    /** @param array<string, float> $values */
    private function weighted(array $values, string $weightSection): float
    {
        return $this->weightedValues($values, $this->section($weightSection));
    }

    /**
     * @param  array<string, float>  $values
     * @param  array<string, mixed>  $weights
     */
    private function weightedValues(array $values, array $weights): float
    {
        $total = 0.0;
        $weightTotal = 0.0;

        foreach ($values as $key => $value) {
            $weight = $weights[$key] ?? null;

            if (! is_numeric($weight) || (float) $weight < 0) {
                throw new UnexpectedValueException("Invalid scoring weight [{$key}] for ".self::VERSION.'.');
            }

            $total += $value * (float) $weight;
            $weightTotal += (float) $weight;
        }

        if ($weightTotal <= 0) {
            throw new UnexpectedValueException('Scoring weights must have a positive total.');
        }

        return $total / $weightTotal;
    }

    /** @return array<string, mixed> */
    private function configuration(): array
    {
        $configuration = config('scoring.versions.'.self::VERSION);

        if (! is_array($configuration)) {
            throw new UnexpectedValueException('The niche opportunity v1 configuration is missing.');
        }

        return $configuration;
    }

    /** @return array<string, mixed> */
    private function section(string $section): array
    {
        $value = config('scoring.versions.'.self::VERSION.'.'.$section);

        if (! is_array($value)) {
            throw new UnexpectedValueException("The scoring configuration section [{$section}] is invalid.");
        }

        return $value;
    }

    private function number(string $key): float
    {
        $value = config('scoring.versions.'.self::VERSION.'.'.$key);

        if (! is_numeric($value)) {
            throw new UnexpectedValueException("The scoring configuration value [{$key}] is invalid.");
        }

        return (float) $value;
    }

    private function score(float $value): float
    {
        return round($this->statistics->clamp($value), 4);
    }

    private function summaryNumber(float $value): float
    {
        return round($value, 6);
    }
}
