<?php

namespace Tests\Unit\Domain\Scoring;

use App\Domain\Scoring\Data\OpportunityScoreResult;
use App\Domain\Scoring\Data\ScoringInput;
use App\Domain\Scoring\Services\NicheOpportunityV1;
use Tests\Fixtures\Scoring\NicheOpportunityV1Fixtures;
use Tests\TestCase;

class NicheOpportunityV1FixtureTest extends TestCase
{
    public function test_required_named_fixtures_express_the_expected_scoring_evidence(): void
    {
        $dominant = $this->score(NicheOpportunityV1Fixtures::strongDemandWithDominantLargeChannelCompetition());
        $reachable = $this->score(NicheOpportunityV1Fixtures::strongReachBySmallChannels());
        $stale = $this->score(NicheOpportunityV1Fixtures::staleLowDemandResults());
        $missing = $this->score(NicheOpportunityV1Fixtures::missingSubscriberCounts());
        $mixed = $this->score(NicheOpportunityV1Fixtures::mixedShortsAndLongForm());
        $insufficient = $this->score(NicheOpportunityV1Fixtures::insufficientSample());

        $this->assertGreaterThan(75, $dominant->demandMomentumScore);
        $this->assertLessThan(30, $dominant->competitionOpportunityScore);
        $this->assertGreaterThanOrEqual(70, $reachable->audienceReachabilityScore);
        $this->assertLessThan(15, $stale->demandMomentumScore);
        $this->assertLessThanOrEqual($stale->demandMomentumScore, $stale->contentFreshnessGapScore);
        $this->assertContains('missing_subscriber_counts', $this->warningCodes($missing));
        $this->assertSame(0.0, $missing->inputSummary['availability']['subscriber_percent']);
        $this->assertContains('mixed_content_formats', $this->warningCodes($mixed));
        $this->assertContains('insufficient_sample', $this->warningCodes($insufficient));
    }

    public function test_one_viral_outlier_cannot_dominate_robust_demand_aggregates(): void
    {
        $baseline = $this->score(NicheOpportunityV1Fixtures::weakResultsWithoutOutlier());
        $outlier = $this->score(NicheOpportunityV1Fixtures::oneViralOutlierAmongWeakResults());

        $this->assertEqualsWithDelta(
            $baseline->inputSummary['statistics']['median_views_per_day'],
            $outlier->inputSummary['statistics']['median_views_per_day'],
            1.0,
        );
        $this->assertLessThan(10, $outlier->demandMomentumScore - $baseline->demandMomentumScore);
        $this->assertLessThan(10, abs($outlier->overallScore - $baseline->overallScore));
    }

    public function test_identical_inputs_produce_identical_results(): void
    {
        $input = NicheOpportunityV1Fixtures::balanced();

        $first = $this->score($input);
        $second = $this->score($input);

        $this->assertEquals($first, $second);
        $this->assertSame($first->inputSummary, $second->inputSummary);
        $this->assertSame($first->warnings, $second->warnings);
    }

    public function test_each_component_is_monotonic_for_its_primary_expected_signal(): void
    {
        $this->assertGreaterThan(
            $this->score(NicheOpportunityV1Fixtures::lowDemand())->demandMomentumScore,
            $this->score(NicheOpportunityV1Fixtures::highDemand())->demandMomentumScore,
        );
        $this->assertGreaterThan(
            $this->score(NicheOpportunityV1Fixtures::dominantCompetition())->competitionOpportunityScore,
            $this->score(NicheOpportunityV1Fixtures::diverseCompetition())->competitionOpportunityScore,
        );
        $this->assertGreaterThan(
            $this->score(NicheOpportunityV1Fixtures::lowReachability())->audienceReachabilityScore,
            $this->score(NicheOpportunityV1Fixtures::highReachability())->audienceReachabilityScore,
        );
        $this->assertGreaterThan(
            $this->score(NicheOpportunityV1Fixtures::recentStrongCoverage())->contentFreshnessGapScore,
            $this->score(NicheOpportunityV1Fixtures::staleStrongCoverage())->contentFreshnessGapScore,
        );
        $this->assertGreaterThan(
            $this->score(NicheOpportunityV1Fixtures::lowCreatorViability())->creatorViabilityScore,
            $this->score(NicheOpportunityV1Fixtures::highCreatorViability())->creatorViabilityScore,
        );
    }

    public function test_missing_partial_and_small_samples_reduce_confidence_and_remain_bounded(): void
    {
        $complete = $this->score(NicheOpportunityV1Fixtures::strongReachBySmallChannels());
        $balanced = $this->score(NicheOpportunityV1Fixtures::balanced());
        $missing = $this->score(NicheOpportunityV1Fixtures::missingSubscriberCounts());
        $partial = $this->score(NicheOpportunityV1Fixtures::partialCollection());
        $insufficient = $this->score(NicheOpportunityV1Fixtures::insufficientSample());

        $this->assertLessThan($complete->confidenceScore, $missing->confidenceScore);
        $this->assertLessThan($balanced->confidenceScore, $partial->confidenceScore);
        $this->assertLessThan($complete->confidenceScore, $insufficient->confidenceScore);
        $this->assertContains('partial_collection', $this->warningCodes($partial));

        foreach ([$complete, $missing, $partial, $insufficient] as $result) {
            foreach ($this->scores($result) as $score) {
                $this->assertGreaterThanOrEqual(0, $score);
                $this->assertLessThanOrEqual(100, $score);
            }
        }
    }

    public function test_v1_formula_identifier_weights_and_thresholds_are_frozen(): void
    {
        $result = $this->score(NicheOpportunityV1Fixtures::balanced());

        $this->assertSame('niche-opportunity-v1', NicheOpportunityV1::VERSION);
        $this->assertSame(NicheOpportunityV1::VERSION, $result->formulaVersion);
        $this->assertSame([
            'demand_momentum' => 0.25,
            'competition_opportunity' => 0.20,
            'audience_reachability' => 0.20,
            'content_freshness_gap' => 0.15,
            'creator_viability' => 0.20,
        ], $result->inputSummary['configuration']['weights']);
        $this->assertSame([
            'minimum' => 10,
            'target' => 25,
            'channel_target' => 12,
        ], $result->inputSummary['configuration']['sample']);
        $this->assertSame([
            'winsor_lower_percentile' => 10,
            'winsor_upper_percentile' => 90,
            'meaningful_views_per_day' => 100,
            'median_views_per_day_low' => 10,
            'median_views_per_day_high' => 10000,
            'upper_quartile_views_per_day_low' => 25,
            'upper_quartile_views_per_day_high' => 25000,
            'recency_half_life_days' => 180,
            'large_channel_subscribers' => 1000000,
            'reach_ratio_low' => 0.25,
            'reach_ratio_high' => 3.0,
            'recent_days' => 90,
            'stale_days' => 365,
            'freshness_gap_max_days' => 730,
            'mixed_format_minimum_each' => 2,
            'availability_warning_ratio' => 0.80,
            'partial_enrichment_ratio' => 0.95,
        ], $result->inputSummary['configuration']['normalization']);
    }

    private function score(ScoringInput $input): OpportunityScoreResult
    {
        return app(NicheOpportunityV1::class)->calculate($input);
    }

    /** @return list<string> */
    private function warningCodes(OpportunityScoreResult $result): array
    {
        return array_column($result->warnings, 'code');
    }

    /** @return list<float> */
    private function scores(OpportunityScoreResult $result): array
    {
        return [
            $result->overallScore,
            $result->demandMomentumScore,
            $result->competitionOpportunityScore,
            $result->audienceReachabilityScore,
            $result->contentFreshnessGapScore,
            $result->creatorViabilityScore,
            $result->confidenceScore,
        ];
    }
}
