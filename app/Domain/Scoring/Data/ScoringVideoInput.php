<?php

namespace App\Domain\Scoring\Data;

final readonly class ScoringVideoInput
{
    public function __construct(
        public int $videoId,
        public int $channelId,
        public ?int $viewCount,
        public ?int $likeCount,
        public ?int $commentCount,
        public ?float $viewsPerDay,
        public ?float $viewsToSubscribersRatio,
        public ?float $ageDays,
        public ?int $subscriberCount,
        public bool $subscriberCountHidden,
        public ?bool $isShort,
        public ?string $categoryId,
        public ?float $lifetimeUploadsPerMonth,
    ) {}
}
