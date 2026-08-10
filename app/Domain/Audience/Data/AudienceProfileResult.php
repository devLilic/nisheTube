<?php

namespace App\Domain\Audience\Data;

final readonly class AudienceProfileResult
{
    /**
     * @param  list<AudienceSignal>  $signals
     * @param  list<string>  $warnings
     */
    public function __construct(
        public string $status,
        public string $language,
        public int $usableCommentCount,
        public ?float $confidenceScore,
        public array $signals,
        public array $warnings,
    ) {}
}
