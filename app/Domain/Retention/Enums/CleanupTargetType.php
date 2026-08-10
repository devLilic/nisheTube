<?php

namespace App\Domain\Retention\Enums;

enum CleanupTargetType: string
{
    case ResearchRun = 'research_run';
    case AnalyzerRun = 'analyzer_run';
    case CommentCollection = 'comment_collection';
    case TranscriptDocument = 'transcript_document';
    case Export = 'export';
}
