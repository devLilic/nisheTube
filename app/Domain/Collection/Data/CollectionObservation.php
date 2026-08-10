<?php

namespace App\Domain\Collection\Data;

use App\Models\ChannelSnapshot;
use App\Models\Video;
use App\Models\VideoSnapshot;

final readonly class CollectionObservation
{
    public function __construct(
        public Video $video,
        public VideoSnapshot $videoSnapshot,
        public ?ChannelSnapshot $channelSnapshot,
    ) {}
}
