<?php

namespace App\Domain\YouTube\Services;

use App\Domain\YouTube\Data\ChannelDetails;
use App\Domain\YouTube\Data\ChannelDetailsBatch;
use App\Domain\YouTube\Enums\YouTubeErrorCode;
use App\Domain\YouTube\Exceptions\YouTubeProviderException;
use DateTimeImmutable;

class YouTubeChannelDetailsNormalizer
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
    ): ChannelDetailsBatch {
        $items = $payload['items'] ?? null;

        if (! is_array($items)) {
            throw new YouTubeProviderException(YouTubeErrorCode::PartialData);
        }

        $requested = array_fill_keys($requestedIds, true);
        $channels = [];
        $skipped = 0;
        $hiddenSubscribers = false;
        $optionalStatisticsMissing = false;

        foreach ($items as $item) {
            $channel = is_array($item) ? $this->normalizeItem($item) : null;

            if ($channel === null || ! isset($requested[$channel->channelId])) {
                $skipped++;

                continue;
            }

            $hiddenSubscribers = $hiddenSubscribers || $channel->subscriberCountHidden;
            $optionalStatisticsMissing = $optionalStatisticsMissing
                || $channel->viewCount === null
                || $channel->videoCount === null
                || ($channel->subscriberCount === null && ! $channel->subscriberCountHidden);
            $channels[$channel->channelId] = $channel;
        }

        $warnings = [];
        $missing = count(array_diff($requestedIds, array_keys($channels)));

        if ($skipped > 0) {
            $warnings[] = "Skipped {$skipped} incomplete or unexpected channel record(s).";
        }

        if ($missing > 0) {
            $warnings[] = "YouTube omitted details for {$missing} requested channel(s).";
        }

        if ($hiddenSubscribers) {
            $warnings[] = 'Some channels hide subscriber counts; those values were stored as missing.';
        }

        if ($optionalStatisticsMissing) {
            $warnings[] = 'Some channel statistics were unavailable and were stored as missing.';
        }

        return new ChannelDetailsBatch(array_values($channels), $collectedAt, $warnings);
    }

    /** @param array<mixed> $item */
    private function normalizeItem(array $item): ?ChannelDetails
    {
        $channelId = $this->values->optionalString($item['id'] ?? null);
        $title = $this->values->optionalString($item['snippet']['title'] ?? null);

        if ($channelId === null || $title === null) {
            return null;
        }

        $hidden = ($item['statistics']['hiddenSubscriberCount'] ?? false) === true;
        $country = $this->values->optionalString($item['snippet']['country'] ?? null);
        $country = $country !== null && preg_match('/^[A-Za-z]{2}$/', $country) === 1
            ? strtoupper($country)
            : null;
        $metadata = array_filter([
            'uploads_playlist_id' => $this->values->optionalString(
                $item['contentDetails']['relatedPlaylists']['uploads'] ?? null,
            ),
            'published_at' => $this->values->optionalString($item['snippet']['publishedAt'] ?? null),
        ], fn (?string $value): bool => $value !== null);

        return new ChannelDetails(
            channelId: $channelId,
            title: $title,
            customUrl: $this->values->optionalString($item['snippet']['customUrl'] ?? null),
            thumbnailUrl: $this->values->thumbnailUrl($item['snippet']['thumbnails'] ?? null),
            country: $country,
            subscriberCount: $hidden
                ? null
                : $this->values->nonNegativeInteger($item['statistics']['subscriberCount'] ?? null),
            viewCount: $this->values->nonNegativeInteger($item['statistics']['viewCount'] ?? null),
            videoCount: $this->values->nonNegativeInteger($item['statistics']['videoCount'] ?? null),
            subscriberCountHidden: $hidden,
            metadata: $metadata,
        );
    }
}
