<?php

namespace App\Domain\Scoring\Services;

use App\Domain\Scoring\Data\OpportunityScoreResult;
use App\Domain\Scoring\Data\ScoringInput;
use App\Models\ResearchEvidenceProfile;
use App\Models\ResearchResultEvidence;
use UnexpectedValueException;

final readonly class NicheOpportunityV2
{
    public const VERSION = 'niche-opportunity-v2';

    public function __construct(
        private NicheOpportunityV1 $baseline,
        private RobustStatistics $statistics,
    ) {}

    public function calculate(ScoringInput $input, ResearchEvidenceProfile $profile): OpportunityScoreResult
    {
        $baseline = $this->baseline->calculate($input);
        $signals = $this->signals($input, $profile, $baseline->inputSummary);
        $demand = $baseline->demandMomentumScore;
        $competition = $this->competition($baseline->competitionOpportunityScore, $signals);
        $reachability = $this->reachability($baseline->audienceReachabilityScore, $signals);
        $freshness = $baseline->contentFreshnessGapScore;
        $viability = $this->viability($baseline->creatorViabilityScore, $signals);
        $confidence = $this->confidence($baseline->confidenceScore, $signals);
        $overall = $this->weighted([
            'demand_momentum' => $demand,
            'competition_opportunity' => $competition,
            'audience_reachability' => $reachability,
            'content_freshness_gap' => $freshness,
            'creator_viability' => $viability,
        ], $this->section('weights'));

        return new OpportunityScoreResult(
            formulaVersion: self::VERSION,
            overallScore: $this->score($overall),
            demandMomentumScore: $this->score($demand),
            competitionOpportunityScore: $this->score($competition),
            audienceReachabilityScore: $this->score($reachability),
            contentFreshnessGapScore: $this->score($freshness),
            creatorViabilityScore: $this->score($viability),
            confidenceScore: $this->score($confidence),
            sampleSize: count($input->videos),
            inputSummary: $this->inputSummary($input, $profile, $baseline->inputSummary, $signals),
            explanations: $this->explanations($signals),
            warnings: $this->warnings($input, $profile, $signals),
        );
    }

    /**
     * @param  array<string, mixed>  $baseline
     * @return array<string, float|int|bool|string|null>
     */
    private function signals(ScoringInput $input, ResearchEvidenceProfile $profile, array $baseline): array
    {
        $videosByChannel = [];
        $visibleSubscribers = 0;
        $smallResults = 0;
        $midResults = 0;
        $largeResults = 0;
        $knownFormats = 0;
        $shorts = 0;
        foreach ($input->videos as $video) {
            $videosByChannel[$video->channelId] = ($videosByChannel[$video->channelId] ?? 0) + 1;
            if ($video->isShort !== null) {
                $knownFormats++;
                $video->isShort ? $shorts++ : null;
            }
            if ($video->subscriberCount === null || $video->subscriberCountHidden) {
                continue;
            }
            $visibleSubscribers++;
            if ($video->subscriberCount < $this->number('normalization.small_channel_subscribers')) {
                $smallResults++;
            }
            if ($video->subscriberCount < $this->number('normalization.mid_channel_subscribers')) {
                $midResults++;
            }
            if ($video->subscriberCount >= $this->number('normalization.large_channel_subscribers')) {
                $largeResults++;
            }
        }

        $sampleSize = max(1, count($input->videos));
        $channelShares = array_map(fn (int $count): float => $count / $sampleSize, $videosByChannel);
        $hhi = array_sum(array_map(fn (float $share): float => $share ** 2, $channelShares));
        $repeated = array_sum(array_filter($videosByChannel, fn (int $count): bool => $count > 1));
        $strictCount = $profile->strict_sample_count;
        $outlier = $profile->outlier_evidence['full']['top_video_share'] ?? null;
        $stability = $profile->stability_evidence['label'] ?? null;

        return [
            'sample_size' => count($input->videos),
            'unique_channels' => count($videosByChannel),
            'channel_hhi' => $hhi,
            'repeated_channel_share' => $repeated / $sampleSize,
            'result_ownership_share' => max($channelShares ?: [1.0]),
            'sub_10k_share' => $smallResults / $sampleSize,
            'sub_100k_share' => $midResults / $sampleSize,
            'large_channel_share' => $largeResults / $sampleSize,
            'subscriber_visibility' => $visibleSubscribers / $sampleSize,
            'format_classification' => $knownFormats / $sampleSize,
            'shorts_share' => $shorts / $sampleSize,
            'strict_relevance_share' => $strictCount / $sampleSize,
            'strict_sample_count' => $strictCount,
            'outlier_top_share' => is_numeric($outlier) ? (float) $outlier : null,
            'stability_label' => is_string($stability) ? $stability : null,
            'has_history' => ($baseline['statistics']['previous_median_views_per_day'] ?? null) !== null,
        ];
    }

    /** @param array<string, float|int|bool|string|null> $signals */
    private function competition(float $baseline, array $signals): float
    {
        return $this->weighted([
            'baseline' => $baseline,
            'concentration' => $this->statistics->inverseLinearScore((float) $signals['channel_hhi'], 0.12, 0.70),
            'repeat_share' => $this->statistics->inverseLinearScore((float) $signals['repeated_channel_share'], 0.15, 0.80),
            'large_channel_share' => $this->statistics->inverseLinearScore((float) $signals['large_channel_share'], 0.05, 0.65),
            'small_channel_proof' => $this->statistics->linearScore((float) $signals['sub_100k_share'], 0.10, 0.65),
        ], ['baseline' => 0.35, 'concentration' => 0.25, 'repeat_share' => 0.15, 'large_channel_share' => 0.15, 'small_channel_proof' => 0.10]);
    }

    /** @param array<string, float|int|bool|string|null> $signals */
    private function reachability(float $baseline, array $signals): float
    {
        return $this->weighted([
            'baseline' => $baseline,
            'visible_subscribers' => ((float) $signals['subscriber_visibility']) * 100,
            'small_channel_proof' => $this->statistics->linearScore((float) $signals['sub_100k_share'], 0.10, 0.65),
            'independence' => $this->statistics->inverseLinearScore((float) $signals['result_ownership_share'], 0.20, 0.75),
        ], ['baseline' => 0.55, 'visible_subscribers' => 0.15, 'small_channel_proof' => 0.20, 'independence' => 0.10]);
    }

    /** @param array<string, float|int|bool|string|null> $signals */
    private function viability(float $baseline, array $signals): float
    {
        $formatRepeatability = $this->statistics->linearScore((float) $signals['format_classification'], 0.50, 1.0);
        $shortsDependence = $this->statistics->inverseLinearScore((float) $signals['shorts_share'], 0.50, 0.95);

        return $this->weighted([
            'baseline' => $baseline,
            'repeat_winners' => $this->statistics->inverseLinearScore((float) $signals['result_ownership_share'], 0.20, 0.75),
            'format_repeatability' => $formatRepeatability,
            'shorts_dependence' => $shortsDependence,
        ], ['baseline' => 0.60, 'repeat_winners' => 0.15, 'format_repeatability' => 0.15, 'shorts_dependence' => 0.10]);
    }

    /** @param array<string, float|int|bool|string|null> $signals */
    private function confidence(float $baseline, array $signals): float
    {
        $outlierIndependence = $signals['outlier_top_share'] === null
            ? 0.0
            : $this->statistics->inverseLinearScore((float) $signals['outlier_top_share'], 0.20, 0.70);
        $stability = match ($signals['stability_label']) {
            'high' => 100.0,
            'medium' => 65.0,
            'low' => 30.0,
            default => 0.0,
        };

        return $this->weighted([
            'base_coverage' => $baseline,
            'sample_size' => $this->statistics->linearScore((float) $signals['sample_size'], 5, $this->number('sample.target')),
            'strict_relevance' => ((float) $signals['strict_relevance_share']) * 100,
            'stability' => $stability,
            'channel_diversity' => $this->statistics->linearScore((float) $signals['unique_channels'], 3, $this->number('sample.channel_target')),
            'subscriber_visibility' => ((float) $signals['subscriber_visibility']) * 100,
            'format_classification' => ((float) $signals['format_classification']) * 100,
            'outlier_independence' => $outlierIndependence,
            'independent_evidence' => $this->statistics->inverseLinearScore((float) $signals['result_ownership_share'], 0.20, 0.75),
        ], $this->section('confidence_weights'));
    }

    /**
     * @param  array<string, mixed>  $baseline
     * @param  array<string, float|int|bool|string|null>  $signals
     * @return array<string, mixed>
     */
    private function inputSummary(ScoringInput $input, ResearchEvidenceProfile $profile, array $baseline, array $signals): array
    {
        $pins = $profile->results()->orderBy('id')->get(['video_snapshot_id', 'channel_snapshot_id'])
            ->map(fn (ResearchResultEvidence $result): array => ['video_snapshot_id' => $result->video_snapshot_id, 'channel_snapshot_id' => $result->channel_snapshot_id])
            ->all();

        return [
            'configuration' => $this->configuration(),
            'calculation' => ['evidence_profile_id' => $profile->id, 'evidence_version' => $profile->evidence_version, 'source_snapshot_pins' => $pins],
            'sample_views' => ['full_sample_count' => $profile->full_sample_count, 'strict_sample_count' => $profile->strict_sample_count],
            'baseline_v1_inputs' => $baseline,
            'statistics' => $signals,
            'component_labels' => [
                'demand_momentum' => $signals['has_history'] ? 'Observed activity and change' : 'Observed activity',
                'competition_opportunity' => 'Competition opportunity',
                'audience_reachability' => 'Audience reachability',
                'content_freshness_gap' => 'Freshness and underserved gap',
                'creator_viability' => 'Creator viability',
            ],
        ];
    }

    /**
     * @param  array<string, float|int|bool|string|null>  $signals
     * @return array<string, string>
     */
    private function explanations(array $signals): array
    {
        $activity = $signals['has_history'] ? 'stored compatible snapshot change' : 'returned-video activity only; no earlier compatible snapshot exists, so this is not momentum';

        return [
            'demand_momentum' => "Demand uses robust lifetime activity and {$activity}. It is observed evidence, not YouTube search volume.",
            'competition_opportunity' => sprintf('Competition combines channel concentration (HHI %.3f), repeated-channel ownership (%.1f%%), large-channel presence, and sub-10K/sub-100K proof.', (float) $signals['channel_hhi'], (float) $signals['repeated_channel_share'] * 100),
            'audience_reachability' => sprintf('Reachability combines subscriber-normalized stored performance with %.1f%% subscriber visibility and small/mid-channel evidence.', (float) $signals['subscriber_visibility'] * 100),
            'content_freshness_gap' => 'Freshness remains separate from demand: it reflects content age and underserved-gap evidence rather than a claim about current demand change.',
            'creator_viability' => sprintf('Creator viability combines repeat-winner independence, stored cadence and robust performance, format repeatability, and %.1f%% Shorts dependence. Access and budget complexity are unavailable rather than inferred.', (float) $signals['shorts_share'] * 100),
        ];
    }

    /**
     * @param  array<string, float|int|bool|string|null>  $signals
     * @return list<array{code: string, message: string}>
     */
    private function warnings(ScoringInput $input, ResearchEvidenceProfile $profile, array $signals): array
    {
        $warnings = [];
        if (! $signals['has_history']) {
            $warnings[] = $this->warning('observed_activity_only', 'No earlier compatible snapshot is pinned, so demand is shown as observed activity rather than momentum.');
        }
        if ((float) $signals['strict_relevance_share'] < 0.50) {
            $warnings[] = $this->warning('limited_strict_relevance', 'Less than half of the stored sample is strictly relevant; strict-sample evidence is limited.');
        }
        if ((float) $signals['subscriber_visibility'] < $this->number('normalization.availability_warning_ratio')) {
            $warnings[] = $this->warning('limited_subscriber_visibility', 'Hidden or missing subscriber counts reduce reachability and confidence.');
        }
        if ((float) $signals['format_classification'] < $this->number('normalization.availability_warning_ratio')) {
            $warnings[] = $this->warning('limited_format_classification', 'Unknown video formats reduce format-repeatability confidence.');
        }
        if ($signals['outlier_top_share'] === null) {
            $warnings[] = $this->warning('outlier_evidence_unavailable', 'Outlier independence is unavailable because the stored sample is too small or incomplete.');
        } elseif ((float) $signals['outlier_top_share'] >= $this->number('normalization.outlier_dependency_high_share')) {
            $warnings[] = $this->warning('high_outlier_dependence', 'One video accounts for a high share of observed views; robust evidence is limited.');
        }
        if ($signals['stability_label'] === null) {
            $warnings[] = $this->warning('stability_unavailable', 'No compatible historical overlap is available for stability evidence.');
        }
        if ($profile->warnings !== null || $input->collectionWarnings !== []) {
            $warnings[] = $this->warning('partial_evidence', 'Some stored collection or evidence signals are partial; missing values were not treated as zero.');
        }

        return $warnings;
    }

    /** @return array{code: string, message: string} */
    private function warning(string $code, string $message): array
    {
        return ['code' => $code, 'message' => $message];
    }

    /**
     * @param  array<string, float>  $values
     * @param  array<string, mixed>  $weights
     */
    private function weighted(array $values, array $weights): float
    {
        $total = 0.0;
        $weightTotal = 0.0;
        foreach ($values as $key => $value) {
            $weight = $weights[$key] ?? null;
            if (! is_numeric($weight) || (float) $weight < 0) {
                throw new UnexpectedValueException("Invalid v2 scoring weight [{$key}].");
            }

            $total += $value * (float) $weight;
            $weightTotal += (float) $weight;
        }

        if ($weightTotal <= 0) {
            throw new UnexpectedValueException('V2 scoring weights must have a positive total.');
        }

        return $total / $weightTotal;
    }

    /** @return array<string, mixed> */
    private function configuration(): array
    {
        $value = config('scoring.versions.'.self::VERSION);
        if (! is_array($value)) {
            throw new UnexpectedValueException('The niche opportunity v2 configuration is missing.');
        }

        return $value;
    }

    /** @return array<string, mixed> */
    private function section(string $section): array
    {
        $value = config('scoring.versions.'.self::VERSION.'.'.$section);
        if (! is_array($value)) {
            throw new UnexpectedValueException("The v2 scoring configuration section [{$section}] is invalid.");
        }

        return $value;
    }

    private function number(string $key): float
    {
        $value = config('scoring.versions.'.self::VERSION.'.'.$key);
        if (! is_numeric($value)) {
            throw new UnexpectedValueException("The v2 scoring configuration value [{$key}] is invalid.");
        }

        return (float) $value;
    }

    private function score(float $value): float
    {
        return round($this->statistics->clamp($value), 4);
    }
}
