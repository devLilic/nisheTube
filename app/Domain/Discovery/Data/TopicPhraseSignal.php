<?php

namespace App\Domain\Discovery\Data;

final readonly class TopicPhraseSignal
{
    /**
     * @param  list<string>  $tokens
     * @param  list<string>  $videoIds
     * @param  list<string>  $seedQueries
     * @param  list<string>  $originalPhrases
     * @param  list<string>  $languages
     * @param  list<string>  $normalizationTransformations
     */
    public function __construct(
        public string $phrase,
        public string $phraseKey,
        public array $tokens,
        public array $videoIds,
        public array $seedQueries,
        public float $strength,
        public array $originalPhrases = [],
        public array $languages = [],
        public array $normalizationTransformations = [],
    ) {}
}
