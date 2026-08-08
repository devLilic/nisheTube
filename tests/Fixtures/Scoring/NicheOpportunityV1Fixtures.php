<?php

namespace Tests\Fixtures\Scoring;

use App\Domain\Scoring\Data\ScoringInput;
use App\Domain\Scoring\Data\ScoringVideoInput;

final class NicheOpportunityV1Fixtures
{
    public static function balanced(): ScoringInput
    {
        return self::input(
            velocities: [420, 510, 620, 740, 880, 1050, 1250, 1500, 1800, 2200, 2700, 3300],
            channelIds: [1, 1, 2, 2, 3, 3, 4, 4, 5, 5, 6, 6],
        );
    }

    public static function strongDemandWithDominantLargeChannelCompetition(): ScoringInput
    {
        return self::input(
            velocities: [7200, 7800, 8500, 9200, 10000, 11000, 12500, 14000, 16000, 18500, 22000, 26000],
            channelIds: [1, 1, 1, 1, 1, 1, 1, 1, 1, 2, 3, 4],
            subscriberCount: static fn (int $index, int $channelId): int => $channelId === 1 ? 5_000_000 : 2_000_000,
            reachRatio: static fn (): float => 0.18,
        );
    }

    public static function strongReachBySmallChannels(): ScoringInput
    {
        return self::input(
            velocities: [900, 1100, 1350, 1600, 1900, 2300, 2800, 3400, 4100, 5000, 6200, 7600],
            channelIds: range(1, 12),
            subscriberCount: static fn (int $index): int => 800 + ($index * 250),
            reachRatio: static fn (int $index): float => 3.5 + ($index / 10),
        );
    }

    public static function staleLowDemandResults(): ScoringInput
    {
        return self::input(
            velocities: [2, 3, 3, 4, 4, 5, 5, 6, 6, 7, 8, 9],
            channelIds: [1, 1, 2, 2, 3, 3, 4, 4, 5, 5, 6, 6],
            ageDays: static fn (int $index): float => 520 + ($index * 25),
        );
    }

    public static function oneViralOutlierAmongWeakResults(): ScoringInput
    {
        return self::input(
            velocities: [18, 19, 20, 20, 21, 21, 22, 22, 23, 24, 25, 1_000_000],
            channelIds: range(1, 12),
        );
    }

    public static function weakResultsWithoutOutlier(): ScoringInput
    {
        return self::input(
            velocities: [18, 19, 20, 20, 21, 21, 22, 22, 23, 24, 25, 26],
            channelIds: range(1, 12),
        );
    }

    public static function missingSubscriberCounts(): ScoringInput
    {
        return self::input(
            velocities: [900, 1100, 1350, 1600, 1900, 2300, 2800, 3400, 4100, 5000, 6200, 7600],
            channelIds: range(1, 12),
            subscriberCount: static fn (): ?int => null,
            reachRatio: static fn (): ?float => null,
            subscribersHidden: true,
        );
    }

    public static function mixedShortsAndLongForm(): ScoringInput
    {
        return self::input(
            velocities: [420, 510, 620, 740, 880, 1050, 1250, 1500, 1800, 2200, 2700, 3300],
            channelIds: [1, 1, 2, 2, 3, 3, 4, 4, 5, 5, 6, 6],
            isShort: static fn (int $index): bool => $index % 2 === 0,
        );
    }

    public static function insufficientSample(): ScoringInput
    {
        return self::input(
            velocities: [700, 900, 1200, 1600],
            channelIds: [1, 2, 3, 4],
        );
    }

    public static function lowDemand(): ScoringInput
    {
        return self::input(
            velocities: array_fill(0, 12, 20),
            channelIds: range(1, 12),
        );
    }

    public static function highDemand(): ScoringInput
    {
        return self::input(
            velocities: array_fill(0, 12, 8_000),
            channelIds: range(1, 12),
        );
    }

    public static function dominantCompetition(): ScoringInput
    {
        return self::input(
            velocities: array_fill(0, 12, 2_000),
            channelIds: [1, 1, 1, 1, 1, 1, 1, 1, 1, 2, 3, 4],
            subscriberCount: static fn (): int => 2_000_000,
        );
    }

    public static function diverseCompetition(): ScoringInput
    {
        return self::input(
            velocities: array_fill(0, 12, 2_000),
            channelIds: range(1, 12),
            subscriberCount: static fn (): int => 50_000,
        );
    }

    public static function lowReachability(): ScoringInput
    {
        return self::input(
            velocities: array_fill(0, 12, 2_000),
            channelIds: range(1, 12),
            subscriberCount: static fn (): int => 2_000_000,
            reachRatio: static fn (): float => 0.10,
        );
    }

    public static function highReachability(): ScoringInput
    {
        return self::input(
            velocities: array_fill(0, 12, 2_000),
            channelIds: range(1, 12),
            subscriberCount: static fn (int $index): int => 1_000 + ($index * 100),
            reachRatio: static fn (): float => 4.0,
        );
    }

    public static function recentStrongCoverage(): ScoringInput
    {
        return self::input(
            velocities: array_fill(0, 12, 8_000),
            channelIds: range(1, 12),
            ageDays: static fn (int $index): float => 10 + $index,
        );
    }

    public static function staleStrongCoverage(): ScoringInput
    {
        return self::input(
            velocities: array_fill(0, 12, 8_000),
            channelIds: range(1, 12),
            ageDays: static fn (int $index): float => 450 + ($index * 10),
        );
    }

    public static function lowCreatorViability(): ScoringInput
    {
        return self::input(
            velocities: [500, 600, 700, 800, 900, 1000, 1100, 1200, 1300, 1400, 1500, 1600],
            channelIds: range(1, 12),
            uploadsPerMonth: static fn (): float => 30,
            categoryId: static fn (): string => '1',
        );
    }

    public static function highCreatorViability(): ScoringInput
    {
        return self::input(
            velocities: [500, 1400, 600, 1500, 700, 1600, 800, 1700, 900, 1800, 1000, 1900],
            channelIds: [1, 1, 2, 2, 3, 3, 4, 4, 5, 5, 6, 6],
            uploadsPerMonth: static fn (): float => 4,
            categoryId: static fn (int $index): string => (string) (($index % 4) + 1),
        );
    }

    public static function partialCollection(): ScoringInput
    {
        $balanced = self::balanced();

        return new ScoringInput(
            requestedResultCount: 25,
            collectedResultCount: 20,
            enrichedResultCount: 12,
            collectionWarnings: ['youtube_partial_data'],
            videos: $balanced->videos,
            previousMedianViewsPerDay: null,
        );
    }

    /**
     * @param  list<float|int>  $velocities
     * @param  list<int>  $channelIds
     * @param  (callable(int, int): ?int)|null  $subscriberCount
     * @param  (callable(int, int): ?float)|null  $reachRatio
     * @param  (callable(int, int): float)|null  $ageDays
     * @param  (callable(int, int): bool)|null  $isShort
     * @param  (callable(int, int): float)|null  $uploadsPerMonth
     * @param  (callable(int, int): string)|null  $categoryId
     */
    private static function input(
        array $velocities,
        array $channelIds,
        ?callable $subscriberCount = null,
        ?callable $reachRatio = null,
        ?callable $ageDays = null,
        ?callable $isShort = null,
        ?callable $uploadsPerMonth = null,
        ?callable $categoryId = null,
        bool $subscribersHidden = false,
    ): ScoringInput {
        $videos = [];

        foreach ($velocities as $offset => $velocity) {
            $index = $offset + 1;
            $channelId = $channelIds[$offset];
            $resolvedAge = $ageDays !== null ? $ageDays($index, $channelId) : 45.0 + ($index * 8);
            $resolvedSubscribers = $subscriberCount !== null
                ? $subscriberCount($index, $channelId)
                : 10_000 + ($channelId * 1_000);
            $resolvedReachRatio = $reachRatio !== null
                ? $reachRatio($index, $channelId)
                : 1.2 + ($index / 20);

            $videos[] = new ScoringVideoInput(
                videoId: $index,
                channelId: $channelId,
                viewCount: (int) round((float) $velocity * $resolvedAge),
                likeCount: $index * 120,
                commentCount: $index * 12,
                viewsPerDay: (float) $velocity,
                viewsToSubscribersRatio: $resolvedReachRatio,
                ageDays: $resolvedAge,
                subscriberCount: $resolvedSubscribers,
                subscriberCountHidden: $subscribersHidden,
                isShort: $isShort !== null ? $isShort($index, $channelId) : false,
                categoryId: $categoryId !== null ? $categoryId($index, $channelId) : (string) (($index % 4) + 1),
                lifetimeUploadsPerMonth: $uploadsPerMonth !== null ? $uploadsPerMonth($index, $channelId) : 4.0,
            );
        }

        return new ScoringInput(
            requestedResultCount: count($videos),
            collectedResultCount: count($videos),
            enrichedResultCount: count($videos),
            collectionWarnings: [],
            videos: $videos,
            previousMedianViewsPerDay: null,
        );
    }
}
