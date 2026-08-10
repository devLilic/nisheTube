<?php

namespace App\Domain\Transcripts\Data;

final readonly class TranscriptStructureSegment
{
    public function __construct(
        public int $position,
        public string $text,
        public int $startOffset,
        public int $endOffset,
        public ?int $startMs,
        public ?int $endMs,
    ) {}
}
