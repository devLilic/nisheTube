<?php

namespace App\Domain\Catalog\ReadModels;

use App\Models\ResearchRun;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

class BuildResearchRunAnalysis
{
    /** @return array<string, mixed> */
    public function handle(ResearchRun $run): array
    {
        /** @var Collection<int, stdClass> $records */
        $records = DB::table('video_snapshots as video_snapshot')
            ->join('videos as video', 'video.id', '=', 'video_snapshot.video_id')
            ->join('channels as channel', 'channel.id', '=', 'video.channel_id')
            ->join('research_run_videos as membership', function ($join) use ($run): void {
                $join->on('membership.video_id', '=', 'video.id')
                    ->where('membership.research_run_id', '=', $run->id);
            })
            ->leftJoin('channel_snapshots as channel_snapshot', function ($join) use ($run): void {
                $join->on('channel_snapshot.channel_id', '=', 'channel.id')
                    ->where('channel_snapshot.research_run_id', '=', $run->id);
            })
            ->where('video_snapshot.research_run_id', $run->id)
            ->orderBy('membership.result_rank')
            ->select([
                'video.provider_video_id',
                'video.title as video_title',
                'video.thumbnail_url as video_thumbnail_url',
                'video.published_at',
                'video.duration_seconds',
                'video.category_id',
                'video.is_short',
                'membership.result_rank',
                'video_snapshot.view_count',
                'video_snapshot.like_count',
                'video_snapshot.comment_count',
                'video_snapshot.age_seconds',
                'video_snapshot.views_per_day',
                'video_snapshot.views_to_subscribers_ratio',
                'video_snapshot.collected_at as video_collected_at',
                'channel.id as channel_database_id',
                'channel.provider_channel_id',
                'channel.title as channel_title',
                'channel.custom_url',
                'channel.thumbnail_url as channel_thumbnail_url',
                'channel.country',
                'channel_snapshot.subscriber_count',
                'channel_snapshot.view_count as channel_view_count',
                'channel_snapshot.video_count as channel_video_count',
                'channel_snapshot.subscriber_count_hidden',
                'channel_snapshot.metadata as channel_metadata',
                'channel_snapshot.collected_at as channel_collected_at',
            ])
            ->get();

        $videos = $records->map(fn (stdClass $record): array => $this->videoRow($record))->all();
        $channels = $records
            ->groupBy(fn (stdClass $record): int => (int) $record->channel_database_id)
            ->map(fn (Collection $channelRecords): array => $this->channelRow($channelRecords))
            ->sortByDesc(fn (array $channel): float => $channel['median_views_per_day'] ?? -1)
            ->values()
            ->all();

        $freshness = $records
            ->flatMap(fn (stdClass $record): array => array_values(array_filter([
                $record->video_collected_at,
                $record->channel_collected_at,
            ])))
            ->map(fn (mixed $timestamp): CarbonImmutable => CarbonImmutable::parse((string) $timestamp))
            ->sort()
            ->values();

        $videoCount = count($videos);
        $channelCount = count($channels);

        return [
            'summary' => [
                'video_count' => $videoCount,
                'channel_count' => $channelCount,
                'coverage_percent' => $run->requested_result_count > 0
                    ? round(($videoCount / $run->requested_result_count) * 100, 1)
                    : 0.0,
                'median_views' => $this->median(array_column($videos, 'view_count')),
                'median_views_per_day' => $this->median(array_column($videos, 'views_per_day')),
                'median_subscribers' => $this->median(array_column($channels, 'subscriber_count')),
                'median_reach_ratio' => $this->median(array_column($videos, 'reach_ratio')),
                'median_engagement_rate' => $this->median(array_column($videos, 'engagement_rate')),
                'hidden_subscriber_channels' => count(array_filter(
                    $channels,
                    fn (array $channel): bool => $channel['subscriber_count_hidden'],
                )),
                'missing_video_metric_count' => count(array_filter(
                    $videos,
                    fn (array $video): bool => in_array(null, [
                        $video['view_count'],
                        $video['like_count'],
                        $video['comment_count'],
                        $video['views_per_day'],
                    ], true),
                )),
                'earliest_collected_at' => $freshness->first()?->toIso8601String(),
                'latest_collected_at' => $freshness->last()?->toIso8601String(),
            ],
            'videos' => $videos,
            'channels' => $channels,
        ];
    }

    /** @return array<string, mixed> */
    private function videoRow(stdClass $record): array
    {
        $views = $this->nullableInt($record->view_count);
        $likes = $this->nullableInt($record->like_count);
        $comments = $this->nullableInt($record->comment_count);

        return [
            'provider_video_id' => (string) $record->provider_video_id,
            'title' => (string) $record->video_title,
            'thumbnail_url' => $record->video_thumbnail_url !== null ? (string) $record->video_thumbnail_url : null,
            'channel_id' => (string) $record->provider_channel_id,
            'channel_title' => (string) $record->channel_title,
            'result_rank' => (int) $record->result_rank,
            'view_count' => $views,
            'like_count' => $likes,
            'comment_count' => $comments,
            'engagement_rate' => $views !== null && $views > 0 && $likes !== null && $comments !== null
                ? round((($likes + $comments) / $views) * 100, 4)
                : null,
            'age_days' => $record->age_seconds !== null ? round(((int) $record->age_seconds) / 86400, 2) : null,
            'views_per_day' => $this->nullableFloat($record->views_per_day),
            'reach_ratio' => $this->nullableFloat($record->views_to_subscribers_ratio),
            'duration_seconds' => $this->nullableInt($record->duration_seconds),
            'category_id' => $record->category_id !== null ? (string) $record->category_id : null,
            'is_short' => $record->is_short !== null ? (bool) $record->is_short : null,
            'published_at' => CarbonImmutable::parse((string) $record->published_at)->toIso8601String(),
            'collected_at' => CarbonImmutable::parse((string) $record->video_collected_at)->toIso8601String(),
        ];
    }

    /**
     * @param  Collection<int, stdClass>  $records
     * @return array<string, mixed>
     */
    private function channelRow(Collection $records): array
    {
        $first = $records->first();
        $metadata = $this->metadata($first->channel_metadata);
        $publishedAt = isset($metadata['published_at']) && is_string($metadata['published_at'])
            ? CarbonImmutable::parse($metadata['published_at'])
            : null;
        $collectedAt = $first->channel_collected_at !== null
            ? CarbonImmutable::parse((string) $first->channel_collected_at)
            : null;
        $channelVideoCount = $this->nullableInt($first->channel_video_count);
        $monthsActive = $publishedAt !== null && $collectedAt !== null
            ? max(($publishedAt->diffInSeconds($collectedAt) / 86400) / 30.4375, 1.0)
            : null;

        return [
            'provider_channel_id' => (string) $first->provider_channel_id,
            'title' => (string) $first->channel_title,
            'thumbnail_url' => $first->channel_thumbnail_url !== null ? (string) $first->channel_thumbnail_url : null,
            'custom_url' => $first->custom_url !== null ? (string) $first->custom_url : null,
            'country' => $first->country !== null ? (string) $first->country : null,
            'subscriber_count' => $this->nullableInt($first->subscriber_count),
            'subscriber_count_hidden' => (bool) ($first->subscriber_count_hidden ?? false),
            'view_count' => $this->nullableInt($first->channel_view_count),
            'video_count' => $channelVideoCount,
            'lifetime_uploads_per_month' => $channelVideoCount !== null && $monthsActive !== null
                ? round($channelVideoCount / $monthsActive, 2)
                : null,
            'sample_video_count' => $records->count(),
            'median_views' => $this->median($records->pluck('view_count')->all()),
            'median_views_per_day' => $this->median($records->pluck('views_per_day')->all()),
            'median_reach_ratio' => $this->median($records->pluck('views_to_subscribers_ratio')->all()),
            'collected_at' => $collectedAt?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function metadata(mixed $metadata): array
    {
        if (is_array($metadata)) {
            return $metadata;
        }

        if (! is_string($metadata)) {
            return [];
        }

        $decoded = json_decode($metadata, true);

        return is_array($decoded) ? $decoded : [];
    }

    /** @param array<int, mixed> $values */
    private function median(array $values): ?float
    {
        $numbers = array_values(array_map(
            fn (mixed $value): float => (float) $value,
            array_filter($values, fn (mixed $value): bool => $value !== null),
        ));

        if ($numbers === []) {
            return null;
        }

        sort($numbers, SORT_NUMERIC);
        $middle = intdiv(count($numbers), 2);

        return count($numbers) % 2 === 0
            ? ($numbers[$middle - 1] + $numbers[$middle]) / 2
            : $numbers[$middle];
    }

    private function nullableInt(mixed $value): ?int
    {
        return $value !== null ? (int) $value : null;
    }

    private function nullableFloat(mixed $value): ?float
    {
        return $value !== null ? (float) $value : null;
    }
}
