<?php

namespace App\Domain\Transcripts\Services;

use App\Domain\Transcripts\Contracts\TranscriptStructureProvider;
use App\Domain\Transcripts\Data\TranscriptStructureInsight;
use App\Domain\Transcripts\Data\TranscriptStructureResult;
use App\Domain\Transcripts\Data\TranscriptStructureSegment;
use Illuminate\Support\Str;

final class DeterministicTranscriptStructureProvider implements TranscriptStructureProvider
{
    private const STOP_WORDS = [
        'en' => ['about', 'after', 'again', 'also', 'and', 'are', 'because', 'before', 'but', 'can', 'for', 'from', 'have', 'into', 'just', 'more', 'not', 'now', 'that', 'the', 'their', 'then', 'there', 'these', 'they', 'this', 'through', 'today', 'video', 'what', 'when', 'where', 'which', 'with', 'would', 'you', 'your'],
        'ro' => ['acest', 'această', 'aici', 'care', 'cele', 'cel', 'cum', 'din', 'este', 'face', 'mai', 'pentru', 'poate', 'sau', 'sunt', 'unde', 'video', 'și'],
        'ru' => ['без', 'более', 'видео', 'где', 'для', 'как', 'можно', 'почему', 'что', 'это', 'или', 'из', 'на', 'по', 'при'],
    ];

    private const CTA_MARKERS = [
        'en' => ['subscribe', 'leave a comment', 'comment below', 'download', 'click the link', 'try it', 'share this'],
        'ro' => ['abonați', 'abonează', 'lasă un comentariu', 'scrie în comentarii', 'descarcă', 'apasă linkul', 'încearcă'],
        'ru' => ['подпишитесь', 'подписывайтесь', 'оставьте комментарий', 'напишите в комментариях', 'скачайте', 'нажмите ссылку', 'попробуйте'],
    ];

    public function name(): string
    {
        return 'deterministic_transcript_structure';
    }

    public function version(): string
    {
        return 'transcript-structure-v1';
    }

    /** @param list<TranscriptStructureSegment> $segments */
    public function analyze(string $plainText, string $declaredLanguage, array $segments): TranscriptStructureResult
    {
        $blocks = $this->blocks($plainText, $segments);
        $words = preg_split('/[^\p{L}\p{N}-]+/u', trim($plainText), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $wordCount = count($words);
        $language = in_array($declaredLanguage, ['en', 'ro', 'ru'], true) ? $declaredLanguage : $this->detectLanguage($plainText);

        if ($wordCount < 30 || $blocks === []) {
            return new TranscriptStructureResult('insufficient', $language, $wordCount, null, [], [
                'At least 30 readable words are required for transcript structure analysis.',
            ]);
        }

        $topics = $this->topics($blocks, $language);
        $insights = [];
        $first = $blocks[0];
        $last = $blocks[array_key_last($blocks)];
        $topicLabels = array_map(fn (array $topic): string => $topic['label'], array_slice($topics, 0, 3));
        $topicSummary = $topicLabels === [] ? 'the main subject' : implode(', ', $topicLabels);

        $insights[] = $this->insight(
            'summary',
            'Transcript overview',
            "The transcript develops {$topicSummary} from its opening through its closing evidence.",
            72,
            $first,
            $last,
        );
        $insights[] = $this->insight(
            'hook',
            'Opening hook',
            'The opening introduces the first framing or promise used to gain attention.',
            78,
            $first,
        );

        foreach (array_slice($topics, 0, 6) as $topic) {
            $insights[] = $this->insight('topic', $topic['label'], 'Repeated transcript term.', $topic['confidence'], $topic['block']);
        }
        foreach ($this->entities($blocks, $language) as $entity) {
            $insights[] = $this->insight('entity', $entity['label'], 'Named entity detected in the stored transcript.', $entity['confidence'], $entity['block']);
        }
        foreach ($this->sections($blocks, $topicLabels) as $section) {
            $insights[] = $section;
        }
        foreach ($blocks as $block) {
            if (str_contains($block->text, '?')) {
                $insights[] = $this->insight('question', Str::limit(trim($block->text), 220, '…'), 'Question detected from punctuation in the source evidence.', 92, $block);
            }
            if ($this->containsAny(Str::lower($block->text), self::CTA_MARKERS[$language] ?? self::CTA_MARKERS['en'])) {
                $insights[] = $this->insight('cta', 'Call to action', 'The speaker asks the viewer to take an explicit next step.', 90, $block);
            }
        }

        $hasCta = collect($insights)->contains(fn (TranscriptStructureInsight $insight): bool => $insight->kind === 'cta');
        $structure = $hasCta ? 'Hook → development → call to action' : 'Hook → development → closing';
        $insights[] = $this->insight(
            'script_structure',
            $structure,
            'A deterministic structure label inferred from the opening, ordered evidence blocks, and closing.',
            $hasCta ? 82 : 74,
            $first,
            $last,
        );

        $warnings = [];
        $status = 'complete';
        if ($wordCount < 80 || count($blocks) < 3 || $declaredLanguage === 'und') {
            $status = 'partial';
            $warnings[] = 'The transcript is short, sparsely segmented, or has an unknown/mixed language, so structure is partial.';
        }
        if ($segments !== [] && collect($segments)->every(fn (TranscriptStructureSegment $segment): bool => $segment->startMs === null)) {
            $warnings[] = 'Evidence has character offsets but no timestamp links because the transcript is untimed.';
        }

        $confidence = min(94, 48 + min(24, $wordCount / 10) + min(12, count($blocks) * 2) + ($language === 'und' ? 0 : 8));
        if ($status === 'partial') {
            $confidence = min(74, $confidence);
        }

        return new TranscriptStructureResult(
            $status,
            $language,
            $wordCount,
            round($confidence, 4),
            $this->limitKinds($insights),
            $warnings,
        );
    }

    /**
     * @param  list<TranscriptStructureSegment>  $segments
     * @return list<TranscriptStructureSegment>
     */
    private function blocks(string $plainText, array $segments): array
    {
        if (count($segments) > 1) {
            return $segments;
        }

        if ($segments === []) {
            return [];
        }

        $parts = preg_split('/(?<=[.!?])\s+/u', trim($plainText), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($parts) < 2) {
            return $segments;
        }

        $blocks = [];
        $cursor = 0;
        foreach ($parts as $index => $part) {
            $start = mb_strpos($plainText, $part, $cursor, 'UTF-8');
            $start = $start === false ? $cursor : $start;
            $end = $start + mb_strlen($part, 'UTF-8');
            $blocks[] = new TranscriptStructureSegment($index + 1, $part, $start, $end, null, null);
            $cursor = $end;
        }

        return $blocks;
    }

    /** @param list<TranscriptStructureSegment> $blocks
     * @return list<array{label:string,confidence:int,block:TranscriptStructureSegment,count:int}>
     */
    private function topics(array $blocks, string $language): array
    {
        $terms = [];
        $stops = self::STOP_WORDS[$language] ?? self::STOP_WORDS['en'];
        foreach ($blocks as $block) {
            $tokens = preg_split('/[^\p{L}\p{N}-]+/u', $block->text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            foreach ($tokens as $token) {
                $key = Str::lower($token);
                if (mb_strlen($key) < 4 || is_numeric($key) || in_array($key, $stops, true)) {
                    continue;
                }
                $terms[$key] ??= ['count' => 0, 'block' => $block];
                $terms[$key]['count']++;
            }
        }
        $ranked = [];
        foreach ($terms as $term => $data) {
            if ($data['count'] < 2) {
                continue;
            }
            $ranked[] = [
                'label' => mb_convert_case($term, MB_CASE_TITLE, 'UTF-8'),
                'confidence' => min(94, 52 + $data['count'] * 8),
                'block' => $data['block'],
                'count' => $data['count'],
            ];
        }
        usort($ranked, fn (array $left, array $right): int => [-$left['count'], $left['label']] <=> [-$right['count'], $right['label']]);

        return $ranked;
    }

    /** @param list<TranscriptStructureSegment> $blocks
     * @return list<array{label:string,confidence:int,block:TranscriptStructureSegment,count:int}>
     */
    private function entities(array $blocks, string $language): array
    {
        $found = [];
        foreach ($blocks as $block) {
            preg_match_all('/(?<![.!?]\s)(?:\p{Lu}[\p{L}\p{M}-]{2,})(?:\s+\p{Lu}[\p{L}\p{M}-]{2,})*/u', $block->text, $matches);
            foreach ($matches[0] as $label) {
                $key = Str::lower($label);
                if (in_array($key, self::STOP_WORDS[$language] ?? [], true)) {
                    continue;
                }
                $found[$key] ??= ['label' => $label, 'count' => 0, 'block' => $block];
                $found[$key]['count']++;
            }
        }
        $entities = [];
        foreach ($found as $entity) {
            if ($entity['count'] < 2 && ! str_contains($entity['label'], ' ')) {
                continue;
            }
            $entities[] = [
                'label' => $entity['label'],
                'confidence' => min(92, 58 + $entity['count'] * 10),
                'block' => $entity['block'],
                'count' => $entity['count'],
            ];
        }
        usort($entities, fn (array $left, array $right): int => [-$left['count'], $left['label']] <=> [-$right['count'], $right['label']]);

        return array_slice($entities, 0, 5);
    }

    /** @param list<TranscriptStructureSegment> $blocks
     * @param  list<string>  $topics
     * @return list<TranscriptStructureInsight>
     */
    private function sections(array $blocks, array $topics): array
    {
        $sectionCount = min(5, max(2, count($blocks) >= 6 ? 3 : 2));
        $size = max(1, (int) ceil(count($blocks) / $sectionCount));
        $chunks = array_chunk($blocks, $size);
        $sections = [];
        foreach ($chunks as $index => $chunk) {
            $first = $chunk[0];
            $last = $chunk[array_key_last($chunk)];
            $label = match ($index) {
                0 => 'Opening',
                count($chunks) - 1 => 'Closing',
                default => 'Development '.($index),
            };
            $topic = $topics[$index] ?? ($topics[0] ?? 'the main subject');
            $sections[] = $this->insight('section', $label, "This section develops {$topic}.", 70, $first, $last);
        }

        return $sections;
    }

    private function insight(
        string $kind,
        string $label,
        ?string $detail,
        float $confidence,
        TranscriptStructureSegment $first,
        ?TranscriptStructureSegment $last = null,
    ): TranscriptStructureInsight {
        $last ??= $first;

        return new TranscriptStructureInsight(
            $kind,
            $label,
            $detail,
            $confidence,
            $first->startOffset,
            $last->endOffset,
            $first->startMs,
            $last->endMs,
        );
    }

    /** @param list<TranscriptStructureInsight> $insights
     * @return list<TranscriptStructureInsight>
     */
    private function limitKinds(array $insights): array
    {
        $limits = ['summary' => 1, 'topic' => 6, 'entity' => 5, 'hook' => 1, 'section' => 5, 'cta' => 3, 'question' => 8, 'script_structure' => 1];
        $counts = [];

        return array_values(array_filter($insights, function (TranscriptStructureInsight $insight) use ($limits, &$counts): bool {
            $counts[$insight->kind] = ($counts[$insight->kind] ?? 0) + 1;

            return $counts[$insight->kind] <= $limits[$insight->kind];
        }));
    }

    /** @param list<string> $needles */
    private function containsAny(string $text, array $needles): bool
    {
        return collect($needles)->contains(fn (string $needle): bool => str_contains($text, $needle));
    }

    private function detectLanguage(string $text): string
    {
        if (preg_match('/\p{Cyrillic}/u', $text) === 1) {
            return 'ru';
        }
        if (preg_match('/[ăâîșşțţ]/iu', $text) === 1 || preg_match('/\b(cum|care|pentru|este|sunt|unde)\b/iu', $text) === 1) {
            return 'ro';
        }

        return preg_match('/\p{Latin}/u', $text) === 1 ? 'en' : 'und';
    }
}
