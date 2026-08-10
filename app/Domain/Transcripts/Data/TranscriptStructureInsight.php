<?php

namespace App\Domain\Transcripts\Data;

final readonly class TranscriptStructureInsight
{
    public function __construct(
        public string $kind,
        public string $label,
        public ?string $detail,
        public float $confidence,
        public int $startOffset,
        public int $endOffset,
        public ?int $startMs,
        public ?int $endMs,
    ) {}
}
