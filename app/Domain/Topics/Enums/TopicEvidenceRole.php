<?php

namespace App\Domain\Topics\Enums;

enum TopicEvidenceRole: string
{
    case Evidence = 'evidence';
    case Example = 'example';
    case Outlier = 'outlier';
    case Competitor = 'competitor';
    case Inspiration = 'inspiration';
    case Counterexample = 'counterexample';
}
