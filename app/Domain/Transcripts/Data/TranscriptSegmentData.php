<?php

namespace App\Domain\Transcripts\Data;

final readonly class TranscriptSegmentData
{
    public function __construct(
        public ?int $startMs,
        public ?int $endMs,
        public string $text,
    ) {}
}
