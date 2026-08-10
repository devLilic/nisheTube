<?php

namespace App\Domain\Audience\Data;

final readonly class AudienceSignal
{
    /** @param list<int> $evidenceCommentIds */
    public function __construct(
        public string $kind,
        public string $label,
        public string $key,
        public float $confidence,
        public int $commentCount,
        public int $occurrenceCount,
        public array $evidenceCommentIds,
    ) {}
}
