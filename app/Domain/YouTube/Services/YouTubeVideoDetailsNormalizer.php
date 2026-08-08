<?php

namespace App\Domain\YouTube\Services;

use App\Domain\YouTube\Data\VideoDetails;
use App\Domain\YouTube\Data\VideoDetailsBatch;
use App\Domain\YouTube\Enums\YouTubeErrorCode;
use App\Domain\YouTube\Exceptions\YouTubeProviderException;
use DateTimeImmutable;

class YouTubeVideoDetailsNormalizer
{
    public function __construct(private readonly YouTubeResourceValueNormalizer $values) {}

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $requestedIds
     */
    public function normalize(
        array $payload,
        array $requestedIds,
        DateTimeImmutable $collectedAt,
    ): VideoDetailsBatch {
        $items = $payload['items'] ?? null;

        if (! is_array($items)) {
            throw new YouTubeProviderException(YouTubeErrorCode::PartialData);
        }

        $requested = array_fill_keys($requestedIds, true);
        $videos = [];
        $skipped = 0;
        $optionalStatisticsMissing = false;

        foreach ($items as $item) {
            $video = is_array($item) ? $this->normalizeItem($item) : null;

            if ($video === null || ! isset($requested[$video->videoId])) {
                $skipped++;

                continue;
            }

            if ($video->viewCount === null || $video->likeCount === null || $video->commentCount === null) {
                $optionalStatisticsMissing = true;
            }

            $videos[$video->videoId] = $video;
        }

        $warnings = [];
        $missing = count(array_diff($requestedIds, array_keys($videos)));

        if ($skipped > 0) {
            $warnings[] = "Skipped {$skipped} incomplete or unexpected video record(s).";
        }

        if ($missing > 0) {
            $warnings[] = "YouTube omitted details for {$missing} requested video(s).";
        }

        if ($optionalStatisticsMissing) {
            $warnings[] = 'Some video statistics were unavailable and were stored as missing.';
        }

        return new VideoDetailsBatch(array_values($videos), $collectedAt, $warnings);
    }

    /** @param array<mixed> $item */
    private function normalizeItem(array $item): ?VideoDetails
    {
        $videoId = $this->values->optionalString($item['id'] ?? null);
        $channelId = $this->values->optionalString($item['snippet']['channelId'] ?? null);
        $channelTitle = $this->values->optionalString($item['snippet']['channelTitle'] ?? null);
        $title = $this->values->optionalString($item['snippet']['title'] ?? null);
        $publishedAt = $this->values->dateTime($item['snippet']['publishedAt'] ?? null);

        if ($videoId === null || $channelId === null || $channelTitle === null || $title === null || $publishedAt === null) {
            return null;
        }

        return new VideoDetails(
            videoId: $videoId,
            channelId: $channelId,
            channelTitle: $channelTitle,
            title: $title,
            thumbnailUrl: $this->values->thumbnailUrl($item['snippet']['thumbnails'] ?? null),
            publishedAt: $publishedAt,
            durationSeconds: $this->values->durationSeconds($item['contentDetails']['duration'] ?? null),
            categoryId: $this->values->optionalString($item['snippet']['categoryId'] ?? null),
            isShort: null,
            viewCount: $this->values->nonNegativeInteger($item['statistics']['viewCount'] ?? null),
            likeCount: $this->values->nonNegativeInteger($item['statistics']['likeCount'] ?? null),
            commentCount: $this->values->nonNegativeInteger($item['statistics']['commentCount'] ?? null),
        );
    }
}
