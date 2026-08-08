<?php

namespace App\Domain\YouTube\Data;

use DateTimeImmutable;

final readonly class VideoDetailsBatch
{
    /**
     * @param  list<VideoDetails>  $videos
     * @param  list<string>  $warnings
     */
    public function __construct(
        public array $videos,
        public DateTimeImmutable $collectedAt,
        public array $warnings = [],
    ) {}
}
