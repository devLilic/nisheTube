<?php

namespace Tests\Unit\Domain\Analyzer;

use App\Domain\Analyzer\Enums\BreakoutClass;
use App\Domain\Analyzer\Services\RelativePerformanceCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RelativePerformanceCalculatorTest extends TestCase
{
    #[DataProvider('classificationBoundaries')]
    public function test_every_threshold_boundary_is_explicit(int $anchorViews, BreakoutClass $expected): void
    {
        $result = (new RelativePerformanceCalculator)->calculate(
            anchorVideoId: 1,
            anchorViews: $anchorViews,
            cohortViewsByVideoId: [2 => 100, 3 => 100, 4 => 100],
            minimumBaselineCount: 3,
            thresholds: self::thresholds(),
        );

        $this->assertSame($expected, $result->anchorClass);
    }

    public function test_anchor_is_excluded_from_baseline_but_included_in_tied_rank_and_percentile(): void
    {
        $result = (new RelativePerformanceCalculator)->calculate(
            anchorVideoId: 1,
            anchorViews: 100,
            cohortViewsByVideoId: [1 => 100, 2 => 100, 3 => 50, 4 => 200],
            minimumBaselineCount: 3,
            thresholds: self::thresholds(),
        );

        $this->assertSame(1.0, $result->anchorMedianRatio);
        $this->assertEqualsWithDelta(100 / (350 / 3), $result->anchorAverageRatio, 0.00000001);
        $this->assertSame(2, $result->recentRank);
        $this->assertSame(50.0, $result->recentPercentile);
        $this->assertSame(4, $result->recentComparisonCount);
        $this->assertSame(BreakoutClass::Normal, $result->anchorClass);
    }

    public function test_anchor_absent_from_cohort_is_added_to_the_comparison_population(): void
    {
        $result = (new RelativePerformanceCalculator)->calculate(
            anchorVideoId: 99,
            anchorViews: 500,
            cohortViewsByVideoId: [1 => 50, 2 => 100, 3 => 200],
            minimumBaselineCount: 3,
            thresholds: self::thresholds(),
        );

        $this->assertSame(1, $result->recentRank);
        $this->assertSame(87.5, $result->recentPercentile);
        $this->assertSame(4, $result->recentComparisonCount);
        $this->assertSame(5.0, $result->anchorMedianRatio);
        $this->assertSame(BreakoutClass::Strong, $result->anchorClass);
    }

    public function test_outlier_rates_use_channel_median_and_exact_strong_breakout_boundaries(): void
    {
        $result = (new RelativePerformanceCalculator)->calculate(
            anchorVideoId: 99,
            anchorViews: 100,
            cohortViewsByVideoId: [1 => 100, 2 => 100, 3 => 100, 4 => 300, 5 => 501],
            minimumBaselineCount: 3,
            thresholds: self::thresholds(),
        );

        $this->assertSame(2, $result->strongCount);
        $this->assertSame(40.0, $result->strongSharePercent);
        $this->assertSame(1, $result->breakoutCount);
        $this->assertSame(20.0, $result->breakoutSharePercent);
        $this->assertSame(BreakoutClass::Strong, $result->cohortClassifications[4]['class']);
        $this->assertSame(BreakoutClass::Breakout, $result->cohortClassifications[5]['class']);
    }

    public function test_bottom_percentile_and_rank_are_deterministic_across_input_order(): void
    {
        $calculator = new RelativePerformanceCalculator;
        $first = $calculator->calculate(
            anchorVideoId: 99,
            anchorViews: 10,
            cohortViewsByVideoId: [1 => 40, 2 => 20, 3 => 30],
            minimumBaselineCount: 3,
            thresholds: self::thresholds(),
        );
        $shuffled = $calculator->calculate(
            anchorVideoId: 99,
            anchorViews: 10,
            cohortViewsByVideoId: [3 => 30, 1 => 40, 2 => 20],
            minimumBaselineCount: 3,
            thresholds: self::thresholds(),
        );

        $this->assertSame(4, $first->recentRank);
        $this->assertSame(12.5, $first->recentPercentile);
        $this->assertSame($first->recentRank, $shuffled->recentRank);
        $this->assertSame($first->recentPercentile, $shuffled->recentPercentile);
        $this->assertSame($first->anchorMedianRatio, $shuffled->anchorMedianRatio);
    }

    public function test_missing_zero_and_insufficient_inputs_return_null_with_specific_warnings(): void
    {
        $missing = (new RelativePerformanceCalculator)->calculate(
            anchorVideoId: 1,
            anchorViews: null,
            cohortViewsByVideoId: [2 => 10, 3 => null],
            minimumBaselineCount: 3,
            thresholds: self::thresholds(),
        );
        $zero = (new RelativePerformanceCalculator)->calculate(
            anchorVideoId: 1,
            anchorViews: 10,
            cohortViewsByVideoId: [2 => 0, 3 => 0, 4 => 0],
            minimumBaselineCount: 3,
            thresholds: self::thresholds(),
        );

        $this->assertNull($missing->anchorMedianRatio);
        $this->assertNull($missing->recentRank);
        $this->assertNotEmpty($missing->anchorWarnings);
        $this->assertNotEmpty($missing->cohortWarnings);
        $this->assertNull($zero->anchorMedianRatio);
        $this->assertNull($zero->anchorClass);
        $this->assertNull($zero->strongSharePercent);
        $this->assertStringContainsString('median is zero', implode(' ', $zero->anchorWarnings));
        $this->assertStringContainsString('median view count is zero', implode(' ', $zero->cohortWarnings));
    }

    /** @return iterable<string, array{int, BreakoutClass}> */
    public static function classificationBoundaries(): iterable
    {
        yield 'below 0.5x' => [49, BreakoutClass::Underperformer];
        yield 'exactly 0.5x' => [50, BreakoutClass::Normal];
        yield 'below 1.5x' => [149, BreakoutClass::Normal];
        yield 'exactly 1.5x' => [150, BreakoutClass::AboveAverage];
        yield 'below 3x' => [299, BreakoutClass::AboveAverage];
        yield 'exactly 3x' => [300, BreakoutClass::Strong];
        yield 'exactly 5x' => [500, BreakoutClass::Strong];
        yield 'above 5x' => [501, BreakoutClass::Breakout];
    }

    /**
     * @return array{
     *     underperformer_max_exclusive: float,
     *     normal_max_exclusive: float,
     *     above_average_max_exclusive: float,
     *     strong_max_inclusive: float
     * }
     */
    private static function thresholds(): array
    {
        return [
            'underperformer_max_exclusive' => 0.5,
            'normal_max_exclusive' => 1.5,
            'above_average_max_exclusive' => 3.0,
            'strong_max_inclusive' => 5.0,
        ];
    }
}
