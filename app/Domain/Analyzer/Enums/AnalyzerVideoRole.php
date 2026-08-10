<?php

namespace App\Domain\Analyzer\Enums;

enum AnalyzerVideoRole: string
{
    case Anchor = 'anchor';
    case ChannelRecentUpload = 'channel_recent_upload';
}
