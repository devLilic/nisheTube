<?php

namespace App\Domain\YouTube\Data;

use InvalidArgumentException;

final readonly class CommentThreadsRequest
{
    public function __construct(
        public string $videoId,
        public int $maxResults = 100,
        public ?string $pageToken = null,
        public ProviderRequestContext $context = new ProviderRequestContext,
    ) {
        if (trim($videoId) === '' || $maxResults < 1 || $maxResults > 100) {
            throw new InvalidArgumentException('Comment pages require a video identifier and between 1 and 100 results.');
        }
    }
}
