<?php

namespace App\Domain\YouTube\Data;

use DateTimeImmutable;

final readonly class ChannelDetailsBatch
{
    /**
     * @param  list<ChannelDetails>  $channels
     * @param  list<string>  $warnings
     */
    public function __construct(
        public array $channels,
        public DateTimeImmutable $collectedAt,
        public array $warnings = [],
    ) {}
}
