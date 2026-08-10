<?php

namespace App\Domain\YouTube\Data;

use DateTimeImmutable;

final readonly class PublicComment
{
    public function __construct(
        public string $commentId,
        public string $text,
        public ?int $likeCount,
        public int $replyCount,
        public ?DateTimeImmutable $publishedAt,
        public ?DateTimeImmutable $updatedAt,
    ) {}
}
