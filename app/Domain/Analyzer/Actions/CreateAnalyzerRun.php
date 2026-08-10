<?php

namespace App\Domain\Analyzer\Actions;

use App\Domain\Analyzer\Enums\AnalyzerRunStatus;
use App\Domain\Analyzer\ValueObjects\AnalyzerNavigationContext;
use App\Domain\Collection\Enums\CollectionCachePolicy;
use App\Domain\Collection\Enums\CollectionRunKind;
use App\Domain\Collection\Enums\CollectionRunStatus;
use App\Domain\Collection\Services\CollectionFreshnessWindow;
use App\Models\AnalyzerRun;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class CreateAnalyzerRun
{
    public function __construct(private CollectionFreshnessWindow $freshnessWindow) {}

    public function handle(
        User $user,
        string $videoId,
        CollectionCachePolicy $cachePolicy,
        string $originKind = 'manual',
        ?string $originReference = null,
        ?AnalyzerNavigationContext $navigationContext = null,
        ?CollectionRunKind $collectionKind = null,
    ): AnalyzerRun {
        return $this->handleTarget($user, 'video', $videoId, $cachePolicy, $originKind, $originReference, $navigationContext, $collectionKind);
    }

    public function handleTarget(
        User $user,
        string $targetKind,
        string $targetProviderId,
        CollectionCachePolicy $cachePolicy,
        string $originKind = 'manual',
        ?string $originReference = null,
        ?AnalyzerNavigationContext $navigationContext = null,
        ?CollectionRunKind $collectionKind = null,
    ): AnalyzerRun {
        return DB::transaction(function () use ($user, $targetKind, $targetProviderId, $cachePolicy, $originKind, $originReference, $navigationContext, $collectionKind): AnalyzerRun {
            $attemptNumber = ((int) $user->analyzerRuns()
                ->where('target_kind', $targetKind)
                ->where('target_provider_id', $targetProviderId)
                ->max('attempt_number')) + 1;
            $freshnessWindow = $this->freshnessWindow->normalize((int) config('analyzer.freshness_window_seconds', 21_600));
            $calculationVersion = (string) config('analyzer.calculation_version', 'video-profile-v1');
            $thresholdVersion = (string) config('analyzer.relative_performance.version', 'video-relative-performance-v1');
            $behaviorVersion = (string) config('analyzer.channel_behavior.version', 'channel-behavior-v1');
            $behaviorContext = $this->behaviorContext($behaviorVersion);
            $recentVideoLimit = max(1, min(200, (int) config('analyzer.recent_video_limit', 30)));
            $collectionRun = $user->collectionRuns()->create([
                'provider' => 'youtube',
                'kind' => $collectionKind ?? ($targetKind === 'channel' ? CollectionRunKind::ChannelAnalysis : CollectionRunKind::VideoAnalysis),
                'status' => CollectionRunStatus::Queued,
                'attempt_number' => $attemptNumber,
                'frozen_request' => [
                    'target_kind' => $targetKind,
                    $targetKind === 'channel' ? 'provider_channel_id' : 'provider_video_id' => $targetProviderId,
                    'recent_video_limit' => $recentVideoLimit,
                ],
                'cache_policy' => $cachePolicy,
                'requested_parts' => [
                    'videos.snippet', 'videos.contentDetails', 'videos.statistics',
                    'channels.snippet', 'channels.contentDetails', 'channels.statistics',
                    'playlistItems.contentDetails',
                ],
                'configuration_context' => [
                    'freshness_window_seconds' => $freshnessWindow,
                    'freshness_policy_version' => 'bounded-observation-freshness-v1',
                    'relative_performance_version' => $thresholdVersion,
                    'channel_behavior' => $behaviorContext,
                ],
                'safe_metadata' => ['origin' => $originKind],
                'requested_count' => $recentVideoLimit + 1,
                'processed_count' => 0,
                'progress_percent' => 0,
            ]);

            return $user->analyzerRuns()->create([
                'target_kind' => $targetKind,
                'target_provider_id' => $targetProviderId,
                'collection_run_id' => $collectionRun->id,
                'origin_kind' => $originKind,
                'origin_reference' => $originReference,
                'navigation_context' => $navigationContext?->toArray(),
                'cache_policy' => $cachePolicy,
                'freshness_window_seconds' => $freshnessWindow,
                'recent_video_limit' => $recentVideoLimit,
                'calculation_version' => $calculationVersion,
                'threshold_version' => $thresholdVersion,
                'behavior_version' => $behaviorVersion,
                'status' => AnalyzerRunStatus::Queued,
                'attempt_number' => $attemptNumber,
                'progress_percent' => 0,
            ]);
        });
    }

    /** @return array<string, mixed> */
    private function behaviorContext(string $version): array
    {
        return [
            'version' => $version,
            'momentum_block_size' => max(1, (int) config('analyzer.channel_behavior.momentum_block_size', 5)),
            'minimum_consistency_sample' => max(1, (int) config('analyzer.channel_behavior.minimum_consistency_sample', 5)),
            'minimum_correlation_sample' => max(2, (int) config('analyzer.channel_behavior.minimum_correlation_sample', 5)),
            'momentum_thresholds' => [
                'declining_max_exclusive' => (float) config('analyzer.channel_behavior.momentum_thresholds.declining_max_exclusive', 0.8),
                'stable_max_inclusive' => (float) config('analyzer.channel_behavior.momentum_thresholds.stable_max_inclusive', 1.2),
            ],
            'consistency_thresholds' => [
                'consistent_minimum' => (float) config('analyzer.channel_behavior.consistency_thresholds.consistent_minimum', 75),
                'mixed_minimum' => (float) config('analyzer.channel_behavior.consistency_thresholds.mixed_minimum', 50),
            ],
            'correlation_thresholds' => [
                'weak_max_exclusive' => (float) config('analyzer.channel_behavior.correlation_thresholds.weak_max_exclusive', 0.3),
                'moderate_max_exclusive' => (float) config('analyzer.channel_behavior.correlation_thresholds.moderate_max_exclusive', 0.7),
            ],
        ];
    }
}
