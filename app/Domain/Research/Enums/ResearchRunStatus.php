<?php

namespace App\Domain\Research\Enums;

enum ResearchRunStatus: string
{
    case Draft = 'draft';
    case Queued = 'queued';
    case Searching = 'searching';
    case Enriching = 'enriching';
    case Scoring = 'scoring';
    case Completed = 'completed';
    case Failed = 'failed';

    public function canTransitionTo(self $next): bool
    {
        if ($this === $next) {
            return true;
        }

        return match ($this) {
            self::Draft => $next === self::Queued,
            self::Queued => in_array($next, [self::Searching, self::Failed], true),
            self::Searching => in_array($next, [self::Enriching, self::Failed], true),
            self::Enriching => in_array($next, [self::Scoring, self::Failed], true),
            self::Scoring => in_array($next, [self::Completed, self::Failed], true),
            self::Completed, self::Failed => false,
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Failed], true);
    }
}
