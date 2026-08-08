<?php

namespace App\Domain\YouTube\Data;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class VideoSearchResult
{
    public string $videoId;

    public string $channelId;

    public string $title;

    public function __construct(
        string $videoId,
        string $channelId,
        string $title,
        public DateTimeImmutable $publishedAt,
    ) {
        $videoId = trim($videoId);
        $channelId = trim($channelId);
        $title = trim($title);

        if ($videoId === '' || $channelId === '' || $title === '') {
            throw new InvalidArgumentException('Search results require video, channel, and title values.');
        }

        $this->videoId = $videoId;
        $this->channelId = $channelId;
        $this->title = $title;
    }
}
