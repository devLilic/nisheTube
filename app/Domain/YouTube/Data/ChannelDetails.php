<?php

namespace App\Domain\YouTube\Data;

use InvalidArgumentException;

final readonly class ChannelDetails
{
    public string $channelId;

    public string $title;

    /**
     * @param  array<string, string>  $metadata
     */
    public function __construct(
        string $channelId,
        string $title,
        public ?string $customUrl,
        public ?string $thumbnailUrl,
        public ?string $country,
        public ?int $subscriberCount,
        public ?int $viewCount,
        public ?int $videoCount,
        public bool $subscriberCountHidden,
        public array $metadata = [],
    ) {
        $channelId = trim($channelId);
        $title = trim($title);

        if ($channelId === '' || $title === '') {
            throw new InvalidArgumentException('Channel details require non-empty identity fields.');
        }

        foreach ([$subscriberCount, $viewCount, $videoCount] as $value) {
            if ($value !== null && $value < 0) {
                throw new InvalidArgumentException('Channel counts cannot be negative.');
            }
        }

        $this->channelId = $channelId;
        $this->title = $title;
    }
}
