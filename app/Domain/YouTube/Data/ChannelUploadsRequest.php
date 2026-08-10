<?php

namespace App\Domain\YouTube\Data;

use InvalidArgumentException;

final readonly class ChannelUploadsRequest
{
    public string $playlistId;

    public function __construct(
        string $playlistId,
        public int $maxResults = 50,
        public ?string $pageToken = null,
        public ProviderRequestContext $context = new ProviderRequestContext,
    ) {
        $playlistId = trim($playlistId);

        if ($playlistId === '') {
            throw new InvalidArgumentException('An uploads playlist identifier is required.');
        }

        if ($maxResults < 1 || $maxResults > 50) {
            throw new InvalidArgumentException('Uploads playlist pages require between 1 and 50 results.');
        }

        $this->playlistId = $playlistId;
    }
}
