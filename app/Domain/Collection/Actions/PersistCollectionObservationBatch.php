<?php

namespace App\Domain\Collection\Actions;

use App\Domain\Catalog\Services\VideoMetricCalculator;
use App\Domain\Collection\Data\CollectionChannelObservation;
use App\Domain\Collection\Data\CollectionObservation;
use App\Domain\Collection\Data\CollectionObservationBatch;
use App\Domain\Collection\Enums\CollectionRunStatus;
use App\Domain\YouTube\Data\ChannelDetails;
use App\Domain\YouTube\Data\ChannelDetailsBatch;
use App\Domain\YouTube\Data\VideoDetails;
use App\Domain\YouTube\Data\VideoDetailsBatch;
use App\Models\Channel;
use App\Models\ChannelSnapshot;
use App\Models\CollectionRun;
use App\Models\ResearchRun;
use App\Models\Video;
use App\Models\VideoSnapshot;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class PersistCollectionObservationBatch
{
    public function __construct(private VideoMetricCalculator $metricCalculator) {}

    public function handle(
        CollectionRun $collectionRun,
        VideoDetailsBatch $videoBatch,
        ChannelDetailsBatch $channelBatch,
        ?ResearchRun $legacyResearchRun = null,
    ): CollectionObservationBatch {
        return DB::transaction(function () use (
            $collectionRun,
            $videoBatch,
            $channelBatch,
            $legacyResearchRun,
        ): CollectionObservationBatch {
            $lockedCollectionRun = CollectionRun::query()->lockForUpdate()->findOrFail($collectionRun->id);

            if ($lockedCollectionRun->status !== CollectionRunStatus::Collecting) {
                throw new DomainException('Observations may only be persisted while a collection run is collecting.');
            }

            if (
                $legacyResearchRun !== null
                && (
                    $legacyResearchRun->collection_run_id !== $lockedCollectionRun->id
                    || $legacyResearchRun->user_id !== $lockedCollectionRun->user_id
                )
            ) {
                throw new DomainException('Legacy Research ownership must match the shared collection run.');
            }

            $channelsByProviderId = [];

            foreach ($channelBatch->channels as $details) {
                $channelsByProviderId[$details->channelId] = $details;
            }

            $observations = [];
            $channelObservations = [];

            foreach ($channelBatch->channels as $details) {
                $channel = $this->persistChannel($details->channelId, $details->title, $details);
                $snapshot = $this->persistChannelSnapshot(
                    $lockedCollectionRun,
                    $legacyResearchRun,
                    $channel,
                    $details,
                    $channelBatch,
                );

                if ($snapshot !== null) {
                    $channelObservations[$details->channelId] = new CollectionChannelObservation($channel, $snapshot);
                }
            }

            foreach ($videoBatch->videos as $details) {
                $channelDetails = $channelsByProviderId[$details->channelId] ?? null;
                $channelObservation = $channelObservations[$details->channelId] ?? null;
                $channel = $channelObservation === null
                    ? $this->persistChannel($details->channelId, $details->channelTitle, $channelDetails)
                    : $channelObservation->channel;
                $channelSnapshot = $channelObservation === null
                    ? $this->persistChannelSnapshot(
                        $lockedCollectionRun,
                        $legacyResearchRun,
                        $channel,
                        $channelDetails,
                        $channelBatch,
                    )
                    : $channelObservation->channelSnapshot;
                $video = $this->persistVideo($channel, $details);
                $metrics = $this->metricCalculator->calculate(
                    $video,
                    $details->viewCount,
                    $channelSnapshot,
                    $videoBatch->collectedAt,
                );
                $videoSnapshot = VideoSnapshot::query()->firstOrCreate(
                    [
                        'collection_run_id' => $lockedCollectionRun->id,
                        'video_id' => $video->id,
                    ],
                    [
                        'research_run_id' => $legacyResearchRun?->id,
                        'view_count' => $details->viewCount,
                        'like_count' => $details->likeCount,
                        'comment_count' => $details->commentCount,
                        'age_seconds' => $metrics->ageSeconds,
                        'views_per_day' => $metrics->viewsPerDay,
                        'views_to_subscribers_ratio' => $metrics->viewsToSubscribersRatio,
                        'collected_at' => $videoBatch->collectedAt,
                    ],
                );
                $observations[$details->videoId] = new CollectionObservation(
                    video: $video,
                    videoSnapshot: $videoSnapshot,
                    channelSnapshot: $channelSnapshot,
                );
            }

            return new CollectionObservationBatch($observations, $channelObservations);
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
        CollectionRun $collectionRun,
        ?ResearchRun $legacyResearchRun,
        Channel $channel,
        ?ChannelDetails $details,
        ChannelDetailsBatch $batch,
    ): ?ChannelSnapshot {
        $existing = ChannelSnapshot::query()
            ->where('collection_run_id', $collectionRun->id)
            ->where('channel_id', $channel->id)
            ->first();

        if ($existing !== null || $details === null) {
            return $existing;
        }

        return ChannelSnapshot::query()->create([
            'channel_id' => $channel->id,
            'research_run_id' => $legacyResearchRun?->id,
            'collection_run_id' => $collectionRun->id,
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
