<?php

namespace App\Domain\Retention\Enums;

enum DeletionOutcome: string
{
    case Eligible = 'eligible';
    case PreservedFavorite = 'preserved_favorite';
    case Deleted = 'deleted';
    case SkippedMissing = 'skipped_missing';
    case SkippedIneligible = 'skipped_ineligible';
}
