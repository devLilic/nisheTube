<?php

namespace App\Domain\Transcripts\Data;

final readonly class TranscriptStructureResult
{
    /**
     * @param  list<TranscriptStructureInsight>  $insights
     * @param  list<string>  $warnings
     */
    public function __construct(
        public string $status,
        public string $language,
        public int $wordCount,
        public ?float $confidenceScore,
        public array $insights,
        public array $warnings,
    ) {}
}
