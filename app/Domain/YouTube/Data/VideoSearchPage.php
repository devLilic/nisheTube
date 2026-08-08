<?php

namespace App\Domain\YouTube\Data;

final readonly class VideoSearchPage
{
    /**
     * @param  list<VideoSearchResult>  $results
     * @param  list<string>  $warnings
     */
    public function __construct(
        public array $results,
        public ?string $nextPageToken = null,
        public ?int $approximateTotalResults = null,
        public array $warnings = [],
    ) {
        if ($approximateTotalResults !== null && $approximateTotalResults < 0) {
            throw new \InvalidArgumentException('The approximate total result count cannot be negative.');
        }
    }

    public function isPartial(): bool
    {
        return $this->warnings !== [];
    }
}
