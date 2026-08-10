<?php

namespace App\Domain\YouTube\Data;

use InvalidArgumentException;

final readonly class ChannelUpload
{
    public string $videoId;

    public function __construct(string $videoId, public int $position)
    {
        $videoId = trim($videoId);

        if ($videoId === '' || $position < 1) {
            throw new InvalidArgumentException('A channel upload requires an identifier and positive source position.');
        }

        $this->videoId = $videoId;
    }
}
