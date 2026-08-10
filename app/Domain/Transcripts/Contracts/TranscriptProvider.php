<?php

namespace App\Domain\Transcripts\Contracts;

use App\Domain\Transcripts\Data\TranscriptParseResult;

interface TranscriptProvider
{
    public function name(): string;

    public function version(): string;

    public function parse(string $sourceText): TranscriptParseResult;
}
