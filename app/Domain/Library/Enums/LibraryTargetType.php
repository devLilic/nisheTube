<?php

namespace App\Domain\Library\Enums;

use App\Models\Channel;
use App\Models\NicheCandidate;
use App\Models\ResearchQuery;
use App\Models\ResearchRun;
use App\Models\Video;
use Illuminate\Database\Eloquent\Model;

enum LibraryTargetType: string
{
    case NicheCandidate = 'niche_candidate';
    case Video = 'video';
    case Channel = 'channel';
    case ResearchQuery = 'research_query';
    case ResearchRun = 'research_run';

    /** @return class-string<Model> */
    public function modelClass(): string
    {
        return match ($this) {
            self::NicheCandidate => NicheCandidate::class,
            self::Video => Video::class,
            self::Channel => Channel::class,
            self::ResearchQuery => ResearchQuery::class,
            self::ResearchRun => ResearchRun::class,
        };
    }

    public static function fromModel(Model $model): self
    {
        return match ($model::class) {
            NicheCandidate::class => self::NicheCandidate,
            Video::class => self::Video,
            Channel::class => self::Channel,
            ResearchQuery::class => self::ResearchQuery,
            ResearchRun::class => self::ResearchRun,
            default => throw new \DomainException('This target type cannot be stored in the library.'),
        };
    }
}
