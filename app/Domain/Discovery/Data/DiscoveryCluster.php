<?php

namespace App\Domain\Discovery\Data;

final readonly class DiscoveryCluster
{
    /**
     * @param  list<string>  $memberPhrases
     * @param  list<string>  $videoIds
     * @param  list<string>  $seedQueries
     */
    public function __construct(
        public string $clusterKey,
        public string $representativePhrase,
        public array $memberPhrases,
        public array $videoIds,
        public array $seedQueries,
        public float $strength,
    ) {}
}
