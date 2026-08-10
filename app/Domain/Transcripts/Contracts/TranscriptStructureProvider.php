<?php

namespace App\Domain\Transcripts\Contracts;

use App\Domain\Transcripts\Data\TranscriptStructureResult;
use App\Domain\Transcripts\Data\TranscriptStructureSegment;

interface TranscriptStructureProvider
{
    public function name(): string;

    public function version(): string;

    /** @param list<TranscriptStructureSegment> $segments */
    public function analyze(string $plainText, string $declaredLanguage, array $segments): TranscriptStructureResult;
}
