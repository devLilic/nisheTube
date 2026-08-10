<?php

namespace App\Domain\Comments\Enums;

enum CommentCollectionStatus: string
{
    case Queued = 'queued';
    case Collecting = 'collecting';
    case Completed = 'completed';
    case Empty = 'empty';
    case Partial = 'partial';
    case CommentsDisabled = 'comments_disabled';
    case Unavailable = 'unavailable';
    case QuotaExhausted = 'quota_exhausted';
    case Failed = 'failed';

    public function isActive(): bool
    {
        return in_array($this, [self::Queued, self::Collecting], true);
    }

    public function isTerminal(): bool
    {
        return ! $this->isActive();
    }
}
