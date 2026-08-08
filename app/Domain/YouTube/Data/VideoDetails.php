<?php

namespace App\Domain\YouTube\Data;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class VideoDetails
{
    public string $videoId;

    public string $channelId;

    public string $channelTitle;

    public string $title;

    public function __construct(
        string $videoId,
        string $channelId,
        string $channelTitle,
        string $title,
        public ?string $thumbnailUrl,
        public DateTimeImmutable $publishedAt,
        public ?int $durationSeconds,
        public ?string $categoryId,
        public ?bool $isShort,
        public ?int $viewCount,
        public ?int $likeCount,
        public ?int $commentCount,
    ) {
        $this->videoId = $this->required($videoId);
        $this->channelId = $this->required($channelId);
        $this->channelTitle = $this->required($channelTitle);
        $this->title = $this->required($title);

        foreach ([$durationSeconds, $viewCount, $likeCount, $commentCount] as $value) {
            if ($value !== null && $value < 0) {
                throw new InvalidArgumentException('Video counts and duration cannot be negative.');
            }
        }
    }

    private function required(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            throw new InvalidArgumentException('Video details require non-empty identity fields.');
        }

        return $value;
    }
}
