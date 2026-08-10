<?php

namespace App\Domain\Analyzer\Enums;

enum AnalyzerRunStatus: string
{
    case Queued = 'queued';
    case FetchingVideo = 'fetching_video';
    case FetchingChannel = 'fetching_channel';
    case LoadingRecentVideos = 'loading_recent_videos';
    case CalculatingMetrics = 'calculating_metrics';
    case SavingAnalysis = 'saving_analysis';
    case Completed = 'completed';
    case Failed = 'failed';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Failed], true);
    }
}
