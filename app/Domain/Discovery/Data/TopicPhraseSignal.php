<?php

namespace App\Domain\Discovery\Data;

final readonly class TopicPhraseSignal
{
    /**
     * @param  list<string>  $tokens
     * @param  list<string>  $videoIds
     * @param  list<string>  $seedQueries
     */
    public function __construct(
        public string $phrase,
        public string $phraseKey,
        public array $tokens,
        public array $videoIds,
        public array $seedQueries,
        public float $strength,
    ) {}
}
