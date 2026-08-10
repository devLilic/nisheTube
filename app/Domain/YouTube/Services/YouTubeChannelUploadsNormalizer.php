<?php

namespace App\Domain\YouTube\Services;

use App\Domain\YouTube\Data\ChannelUpload;
use App\Domain\YouTube\Data\ChannelUploadsPage;
use App\Domain\YouTube\Enums\YouTubeErrorCode;
use App\Domain\YouTube\Exceptions\YouTubeProviderException;

final class YouTubeChannelUploadsNormalizer
{
    /** @param array<string, mixed> $payload */
    public function normalize(array $payload, int $positionOffset = 0): ChannelUploadsPage
    {
        $items = $payload['items'] ?? null;

        if (! is_array($items)) {
            throw new YouTubeProviderException(YouTubeErrorCode::PartialData);
        }

        $uploads = [];
        $seen = [];
        $skipped = 0;

        foreach ($items as $index => $item) {
            $videoId = is_array($item)
                ? ($item['contentDetails']['videoId'] ?? $item['snippet']['resourceId']['videoId'] ?? null)
                : null;

            if (! is_string($videoId) || trim($videoId) === '' || isset($seen[$videoId])) {
                $skipped++;

                continue;
            }

            $videoId = trim($videoId);
            $seen[$videoId] = true;
            $uploads[] = new ChannelUpload($videoId, $positionOffset + $index + 1);
        }

        $nextPageToken = $payload['nextPageToken'] ?? null;
        $nextPageToken = is_string($nextPageToken) && trim($nextPageToken) !== ''
            ? trim($nextPageToken)
            : null;

        return new ChannelUploadsPage(
            uploads: $uploads,
            nextPageToken: $nextPageToken,
            warnings: $skipped === 0 ? [] : ["Skipped {$skipped} unavailable or duplicate playlist item(s)."],
        );
    }
}
