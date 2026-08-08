<?php

namespace App\Domain\Discovery\Contracts;

use App\Domain\Discovery\Data\DiscoveryCluster;
use App\Domain\Discovery\Data\TopicPhraseSignal;

interface ClusteringProvider
{
    /**
     * @param  list<TopicPhraseSignal>  $phrases
     * @return list<DiscoveryCluster>
     */
    public function cluster(array $phrases): array;
}
