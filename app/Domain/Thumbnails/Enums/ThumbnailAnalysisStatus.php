<?php

namespace App\Domain\Thumbnails\Enums;

enum ThumbnailAnalysisStatus: string
{
    case Queued = 'queued';
    case Processing = 'processing';
    case Complete = 'complete';
    case Partial = 'partial';
    case Insufficient = 'insufficient';
    case Failed = 'failed';

    public function isActive(): bool
    {
        return in_array($this, [self::Queued, self::Processing], true);
    }

    public function isTerminal(): bool
    {
        return ! $this->isActive();
    }
}
