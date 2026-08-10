<?php

namespace App\Domain\Collection\Data;

use App\Models\Channel;
use App\Models\ChannelSnapshot;

final readonly class CollectionChannelObservation
{
    public function __construct(
        public Channel $channel,
        public ChannelSnapshot $channelSnapshot,
    ) {}
}
