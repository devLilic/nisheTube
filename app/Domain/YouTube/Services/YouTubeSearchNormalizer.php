<?php

namespace App\Domain\YouTube\Services;

use App\Domain\YouTube\Data\VideoSearchPage;
use App\Domain\YouTube\Data\VideoSearchResult;
use App\Domain\YouTube\Enums\YouTubeErrorCode;
use App\Domain\YouTube\Exceptions\YouTubeProviderException;
use DateTimeImmutable;
use Exception;

class YouTubeSearchNormalizer
{
    /** @param array<string, mixed> $payload */
    public function normalize(array $payload): VideoSearchPage
    {
        $items = $payload['items'] ?? null;

        if (! is_array($items)) {
            throw new YouTubeProviderException(YouTubeErrorCode::PartialData);
        }

        $results = [];
        $skipped = 0;

        foreach ($items as $item) {
            $result = is_array($item) ? $this->normalizeItem($item) : null;

            if ($result === null) {
                $skipped++;

                continue;
            }

            $results[] = $result;
        }

        $nextPageToken = $payload['nextPageToken'] ?? null;
        $nextPageToken = is_string($nextPageToken) && trim($nextPageToken) !== ''
            ? trim($nextPageToken)
            : null;

        $totalResults = $payload['pageInfo']['totalResults'] ?? null;
        $totalResults = is_int($totalResults) && $totalResults >= 0 ? $totalResults : null;

        return new VideoSearchPage(
            results: $results,
            nextPageToken: $nextPageToken,
            approximateTotalResults: $totalResults,
            warnings: $skipped === 0 ? [] : ["Skipped {$skipped} incomplete search result(s)."],
        );
    }

    /**
     * @param  array<mixed>  $item
     */
    private function normalizeItem(array $item): ?VideoSearchResult
    {
        $videoId = $item['id']['videoId'] ?? null;
        $channelId = $item['snippet']['channelId'] ?? null;
        $title = $item['snippet']['title'] ?? null;
        $publishedAt = $item['snippet']['publishedAt'] ?? null;

        if (! is_string($videoId) || ! is_string($channelId) || ! is_string($title) || ! is_string($publishedAt)) {
            return null;
        }

        try {
            return new VideoSearchResult(
                videoId: $videoId,
                channelId: $channelId,
                title: $title,
                publishedAt: new DateTimeImmutable($publishedAt),
            );
        } catch (Exception) {
            return null;
        }
    }
}
