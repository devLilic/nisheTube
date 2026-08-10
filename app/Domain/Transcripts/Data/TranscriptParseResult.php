<?php

namespace App\Domain\Transcripts\Data;

final readonly class TranscriptParseResult
{
    /**
     * @param  list<TranscriptSegmentData>  $segments
     * @param  list<string>  $warnings
     */
    public function __construct(
        public string $status,
        public string $format,
        public string $plainText,
        public array $segments,
        public array $warnings,
    ) {}
}
