<?php

namespace Tests\Unit\Domain\Analyzer;

use App\Domain\Analyzer\Data\ChannelBehaviorResult;
use App\Domain\Analyzer\Services\ChannelBehaviorCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ChannelBehaviorCalculatorTest extends TestCase
{
    /** @return array<string, array{list<float>, string, float}> */
    public static function momentumCases(): array
    {
        return [
            'declining below point eight' => [[70, 70, 70, 70, 70, 100, 100, 100, 100, 100], 'declining', 0.7],
            'stable lower boundary' => [[80, 80, 80, 80, 80, 100, 100, 100, 100, 100], 'stable', 0.8],
            'stable upper boundary' => [[120, 120, 120, 120, 120, 100, 100, 100, 100, 100], 'stable', 1.2],
            'growing above one point two' => [[121, 121, 121, 121, 121, 100, 100, 100, 100, 100], 'growing', 1.21],
        ];
    }

    /** @param list<float> $rates */
    #[DataProvider('momentumCases')]
    public function test_momentum_boundaries_are_deterministic(array $rates, string $class, float $ratio): void
    {
        $result = $this->calculate($this->videos($rates));

        self::assertSame($class, $result->momentumClass);
        self::assertEqualsWithDelta($ratio, $result->momentumRatio, 0.000001);
        self::assertSame(5, $result->momentumRecentCount);
        self::assertSame(5, $result->momentumPreviousCount);
    }

    public function test_consistency_uses_median_absolute_deviation_over_the_median(): void
    {
        $consistent = $this->calculate($this->videos([100, 100, 100, 100, 100]));
        $mixed = $this->calculate($this->videos([50, 50, 100, 150, 150]));
        $volatile = $this->calculate($this->videos([1, 1, 100, 199, 199]));

        self::assertSame('consistent', $consistent->consistencyClass);
        self::assertSame(100.0, $consistent->consistencyScore);
        self::assertSame('mixed', $mixed->consistencyClass);
        self::assertSame(50.0, $mixed->consistencyScore);
        self::assertSame('volatile', $volatile->consistencyClass);
        self::assertEqualsWithDelta(1.0, $volatile->consistencyScore, 0.000001);
    }

    public function test_duration_performance_uses_spearman_rank_and_named_buckets(): void
    {
        $videos = [
            ['views_per_day' => 10.0, 'duration_seconds' => 120],
            ['views_per_day' => 20.0, 'duration_seconds' => 360],
            ['views_per_day' => 30.0, 'duration_seconds' => 720],
            ['views_per_day' => 40.0, 'duration_seconds' => 1500],
            ['views_per_day' => 50.0, 'duration_seconds' => 3000],
        ];
        $result = $this->calculate($videos);

        self::assertSame(5, $result->durationPerformanceSampleCount);
        self::assertSame(1.0, $result->durationPerformanceCorrelation);
        self::assertSame('strong_positive', $result->durationPerformanceClass);
        self::assertSame(['count' => 1, 'median_views_per_day' => 10.0], $result->durationPerformanceBuckets['under_5']);
        self::assertSame(['count' => 1, 'median_views_per_day' => 50.0], $result->durationPerformanceBuckets['40_plus']);
    }

    public function test_missing_small_and_zero_median_inputs_return_insufficient_states(): void
    {
        $result = $this->calculate([
            ['views_per_day' => 0.0, 'duration_seconds' => 60],
            ['views_per_day' => 0.0, 'duration_seconds' => null],
            ['views_per_day' => null, 'duration_seconds' => 180],
        ]);

        self::assertNull($result->momentumRatio);
        self::assertNull($result->momentumClass);
        self::assertNull($result->consistencyScore);
        self::assertNull($result->consistencyClass);
        self::assertNull($result->durationPerformanceCorrelation);
        self::assertNull($result->durationPerformanceClass);
        self::assertNotEmpty($result->warnings);
    }

    /**
     * @param  list<float>  $rates
     * @return list<array{views_per_day: float|null, duration_seconds: int|null}>
     */
    private function videos(array $rates): array
    {
        return array_map(static fn (float $rate, int $index): array => [
            'views_per_day' => $rate,
            'duration_seconds' => ($index + 1) * 60,
        ], $rates, array_keys($rates));
    }

    /** @param list<array{views_per_day: float|null, duration_seconds: int|null}> $videos */
    private function calculate(array $videos): ChannelBehaviorResult
    {
        return (new ChannelBehaviorCalculator)->calculate(
            videos: $videos,
            momentumBlockSize: 5,
            minimumConsistencySample: 5,
            minimumCorrelationSample: 5,
            momentumThresholds: ['declining_max_exclusive' => 0.8, 'stable_max_inclusive' => 1.2],
            consistencyThresholds: ['consistent_minimum' => 75.0, 'mixed_minimum' => 50.0],
            correlationThresholds: ['weak_max_exclusive' => 0.3, 'moderate_max_exclusive' => 0.7],
        );
    }
}
