<?php

namespace App\Domain\Analyzer\Actions;

use App\Domain\Analyzer\Enums\AnalyzerVideoRole;
use App\Domain\Collection\Actions\PersistCollectionObservationBatch;
use App\Domain\Collection\Actions\ResolveReusableObservationSources;
use App\Domain\Collection\Data\CollectionObservation;
use App\Domain\YouTube\Contracts\ChannelUploadsProvider;
use App\Domain\YouTube\Contracts\VideoResearchProvider;
use App\Domain\YouTube\Data\ChannelDetailsBatch;
use App\Domain\YouTube\Data\ChannelUploadsRequest;
use App\Domain\YouTube\Data\ProviderRequestContext;
use App\Domain\YouTube\Data\YouTubeIdBatchRequest;
use App\Domain\YouTube\Enums\YouTubeErrorCode;
use App\Domain\YouTube\Exceptions\YouTubeProviderException;
use App\Models\AnalyzerRun;
use App\Models\AnalyzerRunCohortItem;
use App\Models\AnalyzerRunVideo;
use Illuminate\Support\Facades\DB;

final class CollectAnalyzerRecentCohort
{
    public function handle(
        AnalyzerRun $run,
        ChannelUploadsProvider $uploadsProvider,
        VideoResearchProvider $videoProvider,
        ResolveReusableObservationSources $resolveReusableSources,
        PersistCollectionObservationBatch $persistObservationBatch,
        AppendAnalyzerWarning $appendWarning,
        ResolveVideoCategory $resolveCategory,
    ): AnalyzerRun {
        $playlistId = $run->uploads_playlist_id
            ?? $run->channelSnapshot?->metadata['uploads_playlist_id']
            ?? null;

        if (! is_string($playlistId) || trim($playlistId) === '') {
            $run->update(['cohort_collection_complete' => true]);

            return $appendWarning->handle(
                $run,
                'The author channel did not expose an uploads playlist, so the recent baseline is unavailable.',
            );
        }

        if ($run->uploads_playlist_id === null) {
            $run->update(['uploads_playlist_id' => trim($playlistId)]);
        }

        $run = $run->fresh() ?? $run;
        $context = new ProviderRequestContext(userId: $run->user_id, collectionRunId: $run->collection_run_id);

        try {
            $run = $this->stagePlaylist($run, $uploadsProvider, $context, $appendWarning);
        } catch (YouTubeProviderException $exception) {
            if ($exception->providerCode === YouTubeErrorCode::PlaylistUnavailable) {
                $run->update(['cohort_collection_complete' => true, 'cohort_next_page_token' => null]);

                return $appendWarning->handle(
                    $run,
                    'The uploads playlist is unavailable; anchor and channel data remain usable without a recent baseline.',
                );
            }

            throw $exception;
        }

        $pending = $run->cohortItems()
            ->whereNull('enriched_at')
            ->whereNull('unavailable_at')
            ->orderBy('source_position')
            ->get();

        foreach ($pending->chunk(50) as $items) {
            $ids = array_values($items->map(
                static fn (AnalyzerRunCohortItem $item): string => $item->provider_video_id,
            )->all());
            $cached = $resolveReusableSources->handle($run->collectionRun, $ids);
            $missing = array_values(array_filter(
                $ids,
                static fn (string $id): bool => $cached->observation($id) === null,
            ));
            $observations = $cached->observations;

            if ($missing !== []) {
                $videoBatch = $videoProvider->fetchVideos(new YouTubeIdBatchRequest($missing, $context));
                $channelIds = array_values(array_unique(array_map(
                    static fn ($video): string => $video->channelId,
                    $videoBatch->videos,
                )));
                $channelBatch = $channelIds === []
                    ? new ChannelDetailsBatch([], $videoBatch->collectedAt)
                    : $videoProvider->fetchChannels(new YouTubeIdBatchRequest($channelIds, $context));
                $fresh = $persistObservationBatch->handle($run->collectionRun, $videoBatch, $channelBatch);
                $observations = [...$observations, ...$fresh->observations];

                foreach ([...$videoBatch->warnings, ...$channelBatch->warnings] as $warning) {
                    $run = $appendWarning->handle($run, $warning);
                }
            }

            foreach ($items as $item) {
                $observation = $observations[$item->provider_video_id] ?? null;

                if ($observation === null) {
                    $item->update(['unavailable_at' => now()]);
                    $run = $appendWarning->handle(
                        $run,
                        'One or more recent playlist items were deleted, private, or otherwise unavailable and were excluded.',
                    );

                    continue;
                }

                $this->pin($run, $item, $observation);
                $resolveCategory->handle($observation->video);
                $item->update(['enriched_at' => now()]);
            }
        }

        return $run->fresh() ?? $run;
    }

    private function stagePlaylist(
        AnalyzerRun $run,
        ChannelUploadsProvider $provider,
        ProviderRequestContext $context,
        AppendAnalyzerWarning $appendWarning,
    ): AnalyzerRun {
        while (! $run->cohort_collection_complete) {
            $count = $run->cohortItems()->count();
            $remaining = $run->recent_video_limit - $count;

            if ($remaining <= 0) {
                $run->update(['cohort_collection_complete' => true, 'cohort_next_page_token' => null]);
                break;
            }

            $page = $provider->listUploads(new ChannelUploadsRequest(
                playlistId: (string) $run->uploads_playlist_id,
                maxResults: min(50, $remaining),
                pageToken: $run->cohort_next_page_token,
                context: $context,
            ));
            $offset = $run->cohortItems()->max('source_position') ?? 0;

            DB::transaction(function () use ($run, $page, $offset): void {
                foreach ($page->uploads as $index => $upload) {
                    if ($index + $offset >= $run->recent_video_limit) {
                        break;
                    }

                    AnalyzerRunCohortItem::query()->firstOrCreate([
                        'analyzer_run_id' => $run->id,
                        'provider_video_id' => $upload->videoId,
                    ], ['source_position' => $offset + $index + 1]);
                }

                $collected = $run->cohortItems()->count();
                $complete = $page->nextPageToken === null || $collected >= $run->recent_video_limit;
                $run->update([
                    'cohort_next_page_token' => $complete ? null : $page->nextPageToken,
                    'cohort_collection_complete' => $complete,
                ]);
            });

            foreach ($page->warnings as $warning) {
                $run = $appendWarning->handle($run, $warning);
            }

            $run = $run->fresh() ?? $run;

            if ($page->uploads === [] && $page->nextPageToken !== null) {
                $run->update(['cohort_collection_complete' => true, 'cohort_next_page_token' => null]);
                $run = $appendWarning->handle($run, 'The uploads playlist returned an empty page before the cohort limit.');
            }
        }

        return $run->fresh() ?? $run;
    }

    private function pin(AnalyzerRun $run, AnalyzerRunCohortItem $item, CollectionObservation $observation): void
    {
        AnalyzerRunVideo::query()->firstOrCreate([
            'analyzer_run_id' => $run->id,
            'video_id' => $observation->video->id,
            'role' => AnalyzerVideoRole::ChannelRecentUpload,
        ], [
            'video_snapshot_id' => $observation->videoSnapshot->id,
            'channel_snapshot_id' => $observation->channelSnapshot?->id,
            'source_position' => $item->source_position,
        ]);
    }
}
