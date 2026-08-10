<?php

namespace App\Domain\Collection\Actions;

use App\Domain\Collection\Enums\CollectionCachePolicy;
use App\Domain\Collection\Enums\CollectionRunKind;
use App\Domain\Collection\Enums\CollectionRunStatus;
use App\Domain\Collection\Services\CollectionFreshnessWindow;
use App\Models\CollectionRun;
use App\Models\User;

class CreateResearchCollectionRun
{
    public function __construct(private readonly CollectionFreshnessWindow $freshnessWindow) {}

    /**
     * @param  array<string, mixed>  $frozenRequest
     */
    public function handle(
        User $user,
        int $attemptNumber,
        int $requestedCount,
        array $frozenRequest,
        CollectionCachePolicy $cachePolicy = CollectionCachePolicy::FreshOnly,
        ?int $freshnessWindowSeconds = null,
    ): CollectionRun {
        $freshnessWindowSeconds = $this->freshnessWindow->normalize($freshnessWindowSeconds);

        return $user->collectionRuns()->create([
            'provider' => 'youtube',
            'kind' => CollectionRunKind::SearchEnrichment,
            'status' => CollectionRunStatus::Queued,
            'attempt_number' => $attemptNumber,
            'frozen_request' => $frozenRequest,
            'cache_policy' => $cachePolicy,
            'requested_parts' => [
                'videos.snippet',
                'videos.contentDetails',
                'videos.statistics',
                'channels.snippet',
                'channels.statistics',
            ],
            'configuration_context' => [
                'freshness_window_seconds' => $freshnessWindowSeconds,
                'freshness_policy_version' => 'bounded-observation-freshness-v1',
            ],
            'safe_metadata' => [
                'origin' => 'research',
                'historical_backfill' => false,
            ],
            'requested_count' => $requestedCount,
            'processed_count' => 0,
            'progress_percent' => 0,
        ]);
    }
}
