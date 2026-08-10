<?php

namespace App\Domain\Topics\Enums;

use App\Models\AnalyzerRun;
use App\Models\Channel;
use App\Models\NicheCandidate;
use App\Models\ResearchQuery;
use App\Models\ResearchRun;
use App\Models\Video;
use App\Models\WatchlistItem;
use Illuminate\Database\Eloquent\Model;

enum TopicEvidenceType: string
{
    case Video = 'video';
    case Channel = 'channel';
    case ResearchQuery = 'research_query';
    case ResearchRun = 'research_run';
    case NicheCandidate = 'niche_candidate';
    case AnalyzerRun = 'analyzer_run';
    case WatchlistItem = 'watchlist_item';

    /** @return class-string<Model> */
    public function modelClass(): string
    {
        return match ($this) {
            self::Video => Video::class,
            self::Channel => Channel::class,
            self::ResearchQuery => ResearchQuery::class,
            self::ResearchRun => ResearchRun::class,
            self::NicheCandidate => NicheCandidate::class,
            self::AnalyzerRun => AnalyzerRun::class,
            self::WatchlistItem => WatchlistItem::class,
        };
    }

    /** @return array<string, class-string<Model>> */
    public static function morphMap(): array
    {
        $map = [];
        foreach (self::cases() as $type) {
            $map[$type->value] = $type->modelClass();
        }

        return $map;
    }
}
