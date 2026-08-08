<?php

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Services\VideoMetricCalculator;
use App\Domain\Research\Enums\ResearchRunStatus;
use App\Domain\YouTube\Data\ChannelDetails;
use App\Domain\YouTube\Data\ChannelDetailsBatch;
use App\Domain\YouTube\Data\VideoDetails;
use App\Domain\YouTube\Data\VideoDetailsBatch;
use App\Models\Channel;
use App\Models\ChannelSnapshot;
use App\Models\ResearchRun;
use App\Models\ResearchRunSearchResult;
use App\Models\ResearchRunVideo;
use App\Models\Video;
use Illuminate\Support\Facades\DB;

class PersistResearchRunEnrichmentBatch
{
    public function __construct(private readonly VideoMetricCalculator $metricCalculator) {}

    /** @param list<ResearchRunSearchResult> $searchResults */
    public function handle(
        ResearchRun $run,
        array $searchResults,
        VideoDetailsBatch $videoBatch,
        ChannelDetailsBatch $channelBatch,
    ): ResearchRun {
        return DB::transaction(function () use ($run, $searchResults, $videoBatch, $channelBatch): ResearchRun {
            $lockedRun = ResearchRun::query()->lockForUpdate()->findOrFail($run->id);

            if ($lockedRun->status !== ResearchRunStatus::Enriching) {
                return $lockedRun;
            }

            $resultsByVideoId = [];

            foreach ($searchResults as $result) {
                $resultsByVideoId[$result->provider_video_id] = $result;
            }

            $channelsByProviderId = [];

            foreach ($channelBatch->channels as $details) {
                $channelsByProviderId[$details->channelId] = $details;
            }

            foreach ($videoBatch->videos as $details) {
                $searchResult = $resultsByVideoId[$details->videoId] ?? null;

                if ($searchResult === null) {
                    continue;
                }

                $channelDetails = $channelsByProviderId[$details->channelId] ?? null;
                $channel = $this->persistChannel($details->channelId, $details->channelTitle, $channelDetails);
                $channelSnapshot = $this->persistChannelSnapshot(
                    $lockedRun,
                    $channel,
                    $channelDetails,
                    $channelBatch,
                );
                $video = $this->persistVideo($channel, $details);

                ResearchRunVideo::query()->firstOrCreate(
                    [
                        'research_run_id' => $lockedRun->id,
                        'video_id' => $video->id,
                    ],
                    [
                        'result_rank' => $searchResult->result_rank,
                        'page_number' => $searchResult->page_number,
                        'provider_order' => $searchResult->provider_order,
                        'matched_query_metadata' => [
                            'search_title' => $searchResult->title,
                            'search_channel_id' => $searchResult->provider_channel_id,
                            'search_published_at' => $searchResult->published_at->toISOString(),
                        ],
                    ],
                );

                $metrics = $this->metricCalculator->calculate(
                    $video,
                    $details->viewCount,
                    $channelSnapshot,
                    $videoBatch->collectedAt,
                );

                $lockedRun->videoSnapshots()->firstOrCreate(
                    ['video_id' => $video->id],
                    [
                        'view_count' => $details->viewCount,
                        'like_count' => $details->likeCount,
                        'comment_count' => $details->commentCount,
                        'age_seconds' => $metrics->ageSeconds,
                        'views_per_day' => $metrics->viewsPerDay,
                        'views_to_subscribers_ratio' => $metrics->viewsToSubscribersRatio,
                        'collected_at' => $videoBatch->collectedAt,
                    ],
                );
            }

            $enrichedCount = $lockedRun->videoSnapshots()->count();
            $sampleSize = max(1, $lockedRun->collected_result_count);
            $progress = min(89, max(50, 50 + (int) floor(($enrichedCount / $sampleSize) * 40)));
            $warnings = array_values(array_unique([
                ...($lockedRun->collection_warnings ?? []),
                ...$videoBatch->warnings,
                ...$channelBatch->warnings,
            ]));

            $lockedRun->update([
                'enriched_result_count' => $enrichedCount,
                'progress_percent' => $progress,
                'collection_warnings' => $warnings === [] ? null : $warnings,
            ]);

            return $lockedRun;
        });
    }

    private function persistChannel(
        string $providerChannelId,
        string $fallbackTitle,
        ?ChannelDetails $details,
    ): Channel {
        $timestamp = now();

        Channel::query()->insertOrIgnore([
            'provider' => 'youtube',
            'provider_channel_id' => $providerChannelId,
            'title' => $details->title ?? $fallbackTitle,
            'custom_url' => $details?->customUrl,
            'thumbnail_url' => $details?->thumbnailUrl,
            'country' => $details?->country,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        $channel = Channel::query()
            ->where('provider', 'youtube')
            ->where('provider_channel_id', $providerChannelId)
            ->firstOrFail();

        if ($details !== null) {
            $identity = ['title' => $details->title];

            foreach ([
                'custom_url' => $details->customUrl,
                'thumbnail_url' => $details->thumbnailUrl,
                'country' => $details->country,
            ] as $attribute => $value) {
                if ($value !== null) {
                    $identity[$attribute] = $value;
                }
            }

            $channel->update($identity);
        }

        return $channel;
    }

    private function persistChannelSnapshot(
        ResearchRun $run,
        Channel $channel,
        ?ChannelDetails $details,
        ChannelDetailsBatch $batch,
    ): ?ChannelSnapshot {
        $existing = $run->channelSnapshots()
            ->where('channel_id', $channel->id)
            ->first();

        if ($existing !== null || $details === null) {
            return $existing;
        }

        return $run->channelSnapshots()->create([
            'channel_id' => $channel->id,
            'subscriber_count' => $details->subscriberCount,
            'view_count' => $details->viewCount,
            'video_count' => $details->videoCount,
            'subscriber_count_hidden' => $details->subscriberCountHidden,
            'metadata' => $details->metadata === [] ? null : $details->metadata,
            'collected_at' => $batch->collectedAt,
        ]);
    }

    private function persistVideo(Channel $channel, VideoDetails $details): Video
    {
        $timestamp = now();

        Video::query()->insertOrIgnore([
            'provider' => 'youtube',
            'provider_video_id' => $details->videoId,
            'channel_id' => $channel->id,
            'title' => $details->title,
            'thumbnail_url' => $details->thumbnailUrl,
            'published_at' => $details->publishedAt,
            'duration_seconds' => $details->durationSeconds,
            'category_id' => $details->categoryId,
            'is_short' => $details->isShort,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        $video = Video::query()
            ->where('provider', 'youtube')
            ->where('provider_video_id', $details->videoId)
            ->firstOrFail();
        $identity = [
            'channel_id' => $channel->id,
            'title' => $details->title,
            'published_at' => $details->publishedAt,
        ];

        foreach ([
            'thumbnail_url' => $details->thumbnailUrl,
            'duration_seconds' => $details->durationSeconds,
            'category_id' => $details->categoryId,
            'is_short' => $details->isShort,
        ] as $attribute => $value) {
            if ($value !== null) {
                $identity[$attribute] = $value;
            }
        }

        $video->update($identity);

        return $video;
    }
}
