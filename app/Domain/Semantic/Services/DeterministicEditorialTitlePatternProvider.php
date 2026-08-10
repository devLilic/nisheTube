<?php

namespace App\Domain\Semantic\Services;

use App\Domain\Semantic\Contracts\EditorialTitlePatternProvider;
use App\Domain\Semantic\Data\EditorialTitlePattern;
use Illuminate\Support\Str;

final class DeterministicEditorialTitlePatternProvider implements EditorialTitlePatternProvider
{
    /** @var array<string, array{label: string, pattern: string}> */
    private const PATTERNS = [
        'how_to' => ['label' => 'How-to', 'pattern' => '/(?:^|[\s:—-])(how\s+to|how|cum\s+să|cum|как)(?:\s|$)/u'],
        'question' => ['label' => 'Question', 'pattern' => '/\?|(?:^|\s)(why|what|when|where|which|cine|ce|când|unde|de ce|почему|что|когда|где)(?:\s|$)/u'],
        'numbered_list' => ['label' => 'Numbered list', 'pattern' => '/(?:^|\s)(?:top\s+)?\d{1,3}(?:\s|[.:—-])/u'],
        'comparison' => ['label' => 'Comparison', 'pattern' => '/(?:\s|^)(vs\.?|versus|compared?|comparison|comparat|versus|сравнение|против)(?:\s|$)/u'],
        'guide_tutorial' => ['label' => 'Guide or tutorial', 'pattern' => '/(?:^|\s)(guide|tutorial|walkthrough|ghid|tutorialul|руководство|урок|гайд)(?:\s|$)/u'],
        'review' => ['label' => 'Review', 'pattern' => '/(?:^|\s)(review|recenzie|обзор)(?:\s|$)/u'],
        'challenge' => ['label' => 'Challenge', 'pattern' => '/(?:^|\s)(challenge|provocare|вызов|челлендж)(?:\s|$)/u'],
    ];

    public function version(): string
    {
        return 'editorial-title-patterns-v1';
    }

    public function detect(string $title): array
    {
        $normalized = Str::lower(Str::squish($title));
        $matches = [];

        foreach (self::PATTERNS as $key => $definition) {
            if (preg_match($definition['pattern'], $normalized) === 1) {
                $matches[] = new EditorialTitlePattern($key, $definition['label']);
            }
        }

        return $matches;
    }
}
