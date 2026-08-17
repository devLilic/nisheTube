<?php

namespace App\Domain\Discovery\Data;

final readonly class NormalizedPhrase
{
    /**
     * @param  list<string>  $tokens
     * @param  list<string>  $languages
     * @param  list<string>  $transformations
     */
    public function __construct(
        public string $original,
        public string $key,
        public array $tokens,
        public array $languages,
        public array $transformations,
    ) {}
}
