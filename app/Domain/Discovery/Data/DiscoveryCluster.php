<?php

namespace App\Domain\Discovery\Data;

final readonly class DiscoveryCluster
{
    /**
     * @param  list<string>  $memberPhrases
     * @param  list<string>  $videoIds
     * @param  list<string>  $seedQueries
     * @param  list<string>  $languages
     * @param  list<string>  $normalizationTransformations
     */
    public function __construct(
        public string $clusterKey,
        public string $representativePhrase,
        public array $memberPhrases,
        public array $videoIds,
        public array $seedQueries,
        public float $strength,
        public array $languages = [],
        public array $normalizationTransformations = [],
    ) {}
}
