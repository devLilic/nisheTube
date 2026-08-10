<?php

namespace App\Domain\Audience\Services;

use App\Domain\Audience\Contracts\AudienceSignalProvider;
use App\Domain\Audience\Data\AudienceProfileResult;
use App\Domain\Audience\Data\AudienceSignal;
use App\Domain\Audience\Data\AudienceSignalInput;
use Illuminate\Support\Str;

final class DeterministicAudienceSignalProvider implements AudienceSignalProvider
{
    private const STOP_WORDS = [
        'en' => ['about', 'after', 'and', 'are', 'but', 'can', 'could', 'for', 'from', 'have', 'how', 'into', 'more', 'please', 'should', 'that', 'the', 'this', 'video', 'what', 'when', 'where', 'which', 'with', 'would', 'you', 'your'],
        'ro' => ['acest', 'care', 'cele', 'cel', 'cum', 'din', 'este', 'face', 'mai', 'pentru', 'poate', 'sau', 'sunt', 'unde', 'video', 'și'],
        'ru' => ['без', 'более', 'видео', 'где', 'для', 'как', 'можно', 'почему', 'что', 'это', 'или', 'из', 'на', 'по', 'при'],
    ];

    private const KIND_MARKERS = [
        'repeated_question' => ['?', 'how ', 'what ', 'why ', 'where ', 'when ', 'cum ', 'ce ', 'de ce ', 'unde ', 'как ', 'что ', 'почему ', 'где '],
        'suggestion' => ['should ', 'could ', 'please ', 'suggest', 'try ', 'ar trebui', 'poți ', 'sugest', 'пожалуйста', 'можно ', 'совет'],
        'complaint' => ['problem', 'broken', 'doesn\'t work', 'not work', 'hate ', 'bad ', 'issue', 'problemă', 'nu merge', 'greșit', 'плохо', 'ошибка', 'не работает', 'проблем'],
        'confusion_point' => ['confus', 'unclear', 'don\'t understand', 'do not understand', 'lost me', 'nu înțeleg', 'neclar', 'confuz', 'не понимаю', 'неясно', 'запут'],
    ];

    private const UNSAFE_MARKERS = ['kill yourself', 'kys', 'doxx', 'dox ', 'иди нах', 'убей себя'];

    public function name(): string
    {
        return 'deterministic_comment_terms';
    }

    public function version(): string
    {
        return 'audience-comment-terms-v1';
    }

    public function analyze(array $comments): AudienceProfileResult
    {
        if (count($comments) < 3) {
            return new AudienceProfileResult('insufficient', $this->dominantLanguage($comments)[0], count($comments), null, [], [
                'At least three stored top-level comments are required for Audience Signals.',
            ]);
        }

        [$language, $agreement] = $this->dominantLanguage($comments);
        $documents = [];
        $unsafeCount = 0;
        foreach ($comments as $comment) {
            if ($this->isUnsafe($comment->text)) {
                $unsafeCount++;

                continue;
            }
            $tokens = $this->tokens($comment->text, $language);
            if ($tokens !== []) {
                $documents[] = ['id' => $comment->commentId, 'text' => Str::lower($comment->text), 'tokens' => $tokens];
            }
        }

        $warnings = [];
        if ($unsafeCount > 0) {
            $warnings[] = "{$unsafeCount} comment(s) were excluded because they could produce unsafe or identifying output.";
        }
        if ($documents === []) {
            return new AudienceProfileResult('unsafe', $language, 0, null, [], [...$warnings, 'No safe, meaningful comment text remained for inference.']);
        }
        if (count($documents) < 3) {
            return new AudienceProfileResult('insufficient', $language, count($documents), null, [], [...$warnings, 'Fewer than three safe, meaningful comments remained for inference.']);
        }
        if ($agreement < 0.6) {
            $warnings[] = 'The stored comments contain mixed detected languages; signals use the dominant language.';
        }

        $signals = [];
        $signals = [...$signals, ...$this->signalsFor('topic', $documents, null, $agreement, 6)];
        $signals = [...$signals, ...$this->signalsFor('entity', $documents, null, $agreement, 4, true)];
        foreach (['repeated_question', 'suggestion', 'complaint', 'confusion_point'] as $kind) {
            $signals = [...$signals, ...$this->signalsFor($kind, $documents, self::KIND_MARKERS[$kind], $agreement, 4)];
        }

        if ($signals === []) {
            return new AudienceProfileResult('insufficient', $language, count($documents), null, [], [...$warnings, 'No repeated safe pattern was found in the stored comment sample.']);
        }

        $coverageIds = [];
        foreach ($signals as $signal) {
            foreach ($signal->evidenceCommentIds as $id) {
                $coverageIds[$id] = true;
            }
        }
        $coverage = count($coverageIds) / count($documents);
        $confidence = round(min(95, 20 + min(count($documents), 20) * 2 + $coverage * 25 + $agreement * 15), 4);
        $status = $agreement < 0.6 || $unsafeCount > 0 ? 'partial' : 'complete';

        return new AudienceProfileResult($status, $language, count($documents), $confidence, $signals, $warnings);
    }

    /** @param list<array{id:int,text:string,tokens:list<string>}> $documents
     * @param  list<string>|null  $markers
     * @return list<AudienceSignal>
     */
    private function signalsFor(string $kind, array $documents, ?array $markers, float $agreement, int $limit, bool $entitiesOnly = false): array
    {
        $terms = [];
        foreach ($documents as $document) {
            if ($markers !== null && ! $this->containsMarker($document['text'], $markers)) {
                continue;
            }
            $candidates = $entitiesOnly
                ? array_values(array_filter($document['tokens'], fn (string $token): bool => preg_match('/^[A-Z][\p{L}\p{N}-]+$/u', $token) === 1))
                : $this->ngrams(array_map(fn (string $token): string => Str::lower($token), $document['tokens']));
            foreach (array_unique($candidates) as $term) {
                $key = Str::lower($term);
                $terms[$key] ??= ['occurrences' => 0, 'ids' => []];
                $terms[$key]['occurrences'] += substr_count(' '.implode(' ', array_map(fn (string $token): string => Str::lower($token), $document['tokens'])).' ', ' '.$key.' ');
                $terms[$key]['ids'][$document['id']] = true;
            }
        }

        $ranked = [];
        foreach ($terms as $key => $term) {
            $commentCount = count($term['ids']);
            if ($commentCount < 2) {
                continue;
            }
            $ranked[] = ['key' => $key, 'occurrences' => max($commentCount, $term['occurrences']), 'ids' => array_keys($term['ids'])];
        }
        usort($ranked, fn (array $left, array $right): int => [-count($left['ids']), -$left['occurrences'], $left['key']] <=> [-count($right['ids']), -$right['occurrences'], $right['key']]);

        return array_map(function (array $term) use ($kind, $documents, $agreement): AudienceSignal {
            $commentCount = count($term['ids']);

            return new AudienceSignal(
                $kind,
                mb_convert_case($term['key'], MB_CASE_TITLE, 'UTF-8'),
                $term['key'],
                round(min(95, 30 + ($commentCount / count($documents)) * 50 + $agreement * 15), 4),
                $commentCount,
                $term['occurrences'],
                $term['ids'],
            );
        }, array_slice($ranked, 0, $limit));
    }

    /** @param list<string> $tokens
     * @return list<string>
     */
    private function ngrams(array $tokens): array
    {
        $grams = $tokens;
        for ($index = 0; $index < count($tokens) - 1; $index++) {
            $grams[] = $tokens[$index].' '.$tokens[$index + 1];
        }

        return $grams;
    }

    /** @return list<string> */
    private function tokens(string $text, string $language): array
    {
        $redacted = preg_replace('/(?:https?:\/\/|www\.)\S+|\b[\w.+-]+@[\w.-]+\.[A-Za-z]{2,}\b|@[A-Za-z0-9_]{2,}|(?:\+?\d[\d\s().-]{7,}\d)/u', ' ', $text) ?? '';
        $tokens = preg_split('/[^\p{L}\p{N}-]+/u', trim($redacted), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $stopWords = self::STOP_WORDS[$language] ?? [];

        return array_values(array_filter($tokens, fn (string $token): bool => mb_strlen($token) >= 3
            && ! is_numeric($token)
            && ! str_starts_with($token, '@')
            && ! in_array(Str::lower($token), $stopWords, true)));
    }

    /** @param list<AudienceSignalInput> $comments
     * @return array{string,float}
     */
    private function dominantLanguage(array $comments): array
    {
        if ($comments === []) {
            return ['und', 0.0];
        }
        $counts = [];
        foreach ($comments as $comment) {
            $language = $this->detectLanguage($comment->text);
            $counts[$language] = ($counts[$language] ?? 0) + 1;
        }
        arsort($counts, SORT_NUMERIC);
        $language = (string) array_key_first($counts);

        return [$language, ((int) $counts[$language]) / count($comments)];
    }

    private function detectLanguage(string $text): string
    {
        $normalized = Str::lower($text);
        if (preg_match('/\p{Cyrillic}/u', $normalized) === 1) {
            return 'ru';
        }
        if (preg_match('/[ăâîșşțţ]/u', $normalized) === 1 || preg_match('/\b(cum|care|pentru|este|sunt|unde|problemă)\b/u', $normalized) === 1) {
            return 'ro';
        }

        return preg_match('/\p{Latin}/u', $normalized) === 1 ? 'en' : 'und';
    }

    /** @param list<string> $markers */
    private function containsMarker(string $text, array $markers): bool
    {
        return collect($markers)->contains(fn (string $marker): bool => str_contains($text, $marker));
    }

    private function isUnsafe(string $text): bool
    {
        $normalized = Str::lower($text);

        return $this->containsMarker($normalized, self::UNSAFE_MARKERS)
            || preg_match('/(?:https?:\/\/|www\.)\S+|\b[\w.+-]+@[\w.-]+\.[A-Za-z]{2,}\b|@[A-Za-z0-9_]{2,}|(?:\+?\d[\d\s().-]{7,}\d)/u', $text) === 1;
    }
}
