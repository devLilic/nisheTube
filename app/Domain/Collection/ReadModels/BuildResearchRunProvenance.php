<?php

namespace App\Domain\Collection\ReadModels;

use App\Models\CollectionRun;
use App\Models\ResearchRun;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use stdClass;

class BuildResearchRunProvenance
{
    /** @return array<string, mixed> */
    public function handle(ResearchRun $run): array
    {
        /** @var CollectionRun|null $collectionRun */
        $collectionRun = $run->collectionRun()->first();

        if ($collectionRun === null) {
            return [
                'state' => $run->status->isTerminal() ? 'error' : 'loading',
                'message' => $run->status->isTerminal()
                    ? 'This saved run has no collection source context. Its existing metrics remain unchanged.'
                    : 'Collection source context is being prepared for this run.',
                'source' => null,
            ];
        }

        /** @var stdClass $sources */
        $sources = DB::table('research_run_videos as membership')
            ->leftJoin('video_snapshots as video_source', 'video_source.id', '=', 'membership.video_snapshot_id')
            ->leftJoin('channel_snapshots as channel_source', 'channel_source.id', '=', 'membership.channel_snapshot_id')
            ->where('membership.research_run_id', $run->id)
            ->selectRaw('COUNT(*) as result_count')
            ->selectRaw('SUM(CASE WHEN membership.video_snapshot_id IS NOT NULL THEN 1 ELSE 0 END) as pinned_video_count')
            ->selectRaw('SUM(CASE WHEN membership.channel_snapshot_id IS NOT NULL THEN 1 ELSE 0 END) as pinned_channel_count')
            ->selectRaw('COUNT(DISTINCT video_source.id) as video_source_count')
            ->selectRaw('COUNT(DISTINCT channel_source.id) as channel_source_count')
            ->selectRaw('COUNT(DISTINCT CASE WHEN video_source.collection_run_id = ? THEN video_source.id END) as fresh_video_count', [$collectionRun->id])
            ->selectRaw('COUNT(DISTINCT CASE WHEN channel_source.collection_run_id = ? THEN channel_source.id END) as fresh_channel_count', [$collectionRun->id])
            ->selectRaw('COUNT(DISTINCT CASE WHEN video_source.collection_run_id <> ? THEN video_source.id END) as cached_video_count', [$collectionRun->id])
            ->selectRaw('COUNT(DISTINCT CASE WHEN channel_source.collection_run_id <> ? THEN channel_source.id END) as cached_channel_count', [$collectionRun->id])
            ->selectRaw('MIN(video_source.collected_at) as earliest_video_at')
            ->selectRaw('MAX(video_source.collected_at) as latest_video_at')
            ->selectRaw('MIN(channel_source.collected_at) as earliest_channel_at')
            ->selectRaw('MAX(channel_source.collected_at) as latest_channel_at')
            ->first();
        $quota = DB::table('api_usage_events')
            ->where('collection_run_id', $collectionRun->id)
            ->selectRaw('COUNT(*) as attempt_count, COALESCE(SUM(estimated_cost), 0) as estimated_cost')
            ->first();
        $endpoints = DB::table('api_usage_events')
            ->where('collection_run_id', $collectionRun->id)
            ->selectRaw('endpoint, quota_bucket, SUM(request_count) as request_count, SUM(estimated_cost) as estimated_cost')
            ->groupBy('endpoint', 'quota_bucket')
            ->orderBy('endpoint')
            ->limit(20)
            ->get()
            ->map(fn (stdClass $event): array => [
                'endpoint' => (string) $event->endpoint,
                'quota_bucket' => (string) $event->quota_bucket,
                'request_count' => (int) $event->request_count,
                'estimated_cost' => (int) $event->estimated_cost,
            ])
            ->all();

        $videoCount = (int) ($sources->video_source_count ?? 0);
        $channelCount = (int) ($sources->channel_source_count ?? 0);
        $freshVideoCount = (int) ($sources->fresh_video_count ?? 0);
        $freshChannelCount = (int) ($sources->fresh_channel_count ?? 0);
        $cachedVideoCount = (int) ($sources->cached_video_count ?? 0);
        $cachedChannelCount = (int) ($sources->cached_channel_count ?? 0);
        $resultCount = (int) ($sources->result_count ?? 0);
        $pinnedVideoCount = (int) ($sources->pinned_video_count ?? 0);
        $pinnedChannelCount = (int) ($sources->pinned_channel_count ?? 0);
        $warnings = $collectionRun->warnings ?? [];
        $observationCount = $videoCount + $channelCount;
        $cachedObservationCount = $cachedVideoCount + $cachedChannelCount;
        $freshObservationCount = $freshVideoCount + $freshChannelCount;
        $freshnessState = match (true) {
            $observationCount === 0 => 'empty',
            $cachedObservationCount === 0 => 'fresh',
            $freshObservationCount === 0 => 'cached',
            default => 'mixed',
        };
        $observedFrom = $this->earliest($sources->earliest_video_at, $sources->earliest_channel_at);
        $observedTo = $this->latest($sources->latest_video_at, $sources->latest_channel_at);
        $state = match (true) {
            $collectionRun->status->value === 'failed' && $observationCount === 0 => 'error',
            $observationCount === 0 && ! $run->status->isTerminal() => 'loading',
            $observationCount === 0 => 'empty',
            $warnings !== [], $pinnedVideoCount < $resultCount, $pinnedChannelCount < $resultCount => 'partial',
            default => 'ready',
        };

        return [
            'state' => $state,
            'message' => match ($state) {
                'loading' => 'Waiting for immutable video and channel observations.',
                'empty' => 'This run completed without reusable video or channel observations.',
                'partial' => 'Available observations are pinned below; missing sources remain explicit and are not treated as zero.',
                'error' => $collectionRun->error_message
                    ?? 'The provider collection failed before reusable observations were saved.',
                default => match ($freshnessState) {
                    'cached' => 'This result reused sufficiently fresh observations and preserves their original observation times.',
                    'mixed' => 'This result combines newly captured and sufficiently fresh cached observations, each pinned to its original time.',
                    default => 'This Research result is pinned to newly captured immutable observations used by its analysis.',
                },
            },
            'source' => [
                'public_id' => $collectionRun->public_id,
                'provider' => $collectionRun->provider,
                'kind' => $collectionRun->kind->value,
                'status' => $collectionRun->status->value,
                'cache_policy' => $collectionRun->cache_policy->value,
                'freshness_state' => $freshnessState,
                'freshness_window_seconds' => (int) ($collectionRun->configuration_context['freshness_window_seconds'] ?? 0),
                'historical_backfill' => (bool) ($collectionRun->safe_metadata['historical_backfill'] ?? false),
                'observed_from' => $observedFrom,
                'observed_to' => $observedTo,
                'video_observation_count' => $videoCount,
                'channel_observation_count' => $channelCount,
                'fresh_observation_count' => $freshObservationCount,
                'cached_observation_count' => $cachedObservationCount,
                'result_count' => $resultCount,
                'pinned_video_count' => $pinnedVideoCount,
                'pinned_channel_count' => $pinnedChannelCount,
                'quota_attempt_count' => (int) $quota->attempt_count,
                'quota_estimated_cost' => (int) $quota->estimated_cost,
                'endpoints' => $endpoints,
                'warnings' => $warnings,
                'groups' => [
                    [
                        'key' => 'api',
                        'label' => 'YouTube Data',
                        'description' => 'Public video and channel fields returned by the provider at the observation time.',
                    ],
                    [
                        'key' => 'calculated',
                        'label' => 'Calculated Metrics',
                        'description' => 'Deterministic age, daily-rate, reach, aggregate, and score inputs derived from these stored observations.',
                    ],
                ],
            ],
        ];
    }

    private function earliest(mixed ...$timestamps): ?string
    {
        $values = $this->timestamps($timestamps);

        return $values === [] ? null : min($values)->toIso8601String();
    }

    private function latest(mixed ...$timestamps): ?string
    {
        $values = $this->timestamps($timestamps);

        return $values === [] ? null : max($values)->toIso8601String();
    }

    /**
     * @param  array<array-key, mixed>  $timestamps
     * @return list<CarbonImmutable>
     */
    private function timestamps(array $timestamps): array
    {
        return array_values(array_map(
            fn (mixed $timestamp): CarbonImmutable => CarbonImmutable::parse((string) $timestamp),
            array_filter($timestamps, fn (mixed $timestamp): bool => $timestamp !== null),
        ));
    }
}
