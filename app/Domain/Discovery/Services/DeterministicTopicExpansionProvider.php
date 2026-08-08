<?php

namespace App\Domain\Discovery\Services;

use App\Domain\Discovery\Contracts\TopicExpansionProvider;
use App\Domain\Discovery\Data\TopicPhraseSignal;
use Illuminate\Support\Str;

class DeterministicTopicExpansionProvider implements TopicExpansionProvider
{
    private const PHRASE_LIMIT = 100;

    private const STOP_WORDS = [
        'about', 'after', 'best', 'from', 'have', 'into', 'more', 'that', 'this', 'video', 'with',
        'care', 'cele', 'cum', 'din', 'este', 'pentru',
        'более', 'видео', 'для', 'как', 'это',
    ];

    public function expand(array $signals): array
    {
        /** @var array<string, array{phrase: string, tokens: list<string>, videos: array<string, true>, seeds: array<string, true>, strength: float}> $phrases */
        $phrases = [];

        foreach ($signals as $signal) {
            $tokens = $this->tokens($signal->observation->title);

            foreach ($this->ngrams($tokens) as $ngram) {
                $phrase = implode(' ', $ngram);
                $key = Str::lower($phrase);
                $phrases[$key] ??= [
                    'phrase' => $phrase,
                    'tokens' => $ngram,
                    'videos' => [],
                    'seeds' => [],
                    'strength' => 0.0,
                ];
                $phrases[$key]['videos'][$signal->observation->providerVideoId] = true;
                $phrases[$key]['seeds'][$signal->observation->seedQuery] = true;
                $phrases[$key]['strength'] += $signal->strength;
            }
        }

        $expanded = array_map(
            fn (string $key, array $phrase): TopicPhraseSignal => new TopicPhraseSignal(
                phrase: $phrase['phrase'],
                phraseKey: $key,
                tokens: $phrase['tokens'],
                videoIds: array_keys($phrase['videos']),
                seedQueries: array_keys($phrase['seeds']),
                strength: round($phrase['strength'], 4),
            ),
            array_keys($phrases),
            array_values($phrases),
        );

        usort($expanded, fn (TopicPhraseSignal $left, TopicPhraseSignal $right): int => [
            -$left->strength,
            $left->phraseKey,
        ] <=> [
            -$right->strength,
            $right->phraseKey,
        ]);

        return array_slice($expanded, 0, self::PHRASE_LIMIT);
    }

    /** @return list<string> */
    private function tokens(string $title): array
    {
        $normalized = preg_replace('/[^\p{L}\p{N}]+/u', ' ', Str::lower($title)) ?? '';
        $tokens = preg_split('/\s+/u', trim($normalized), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_filter(
            $tokens,
            fn (string $token): bool => mb_strlen($token) >= 3 && ! in_array($token, self::STOP_WORDS, true),
        ));
    }

    /**
     * @param  list<string>  $tokens
     * @return list<list<string>>
     */
    private function ngrams(array $tokens): array
    {
        $ngrams = [];

        foreach ([2, 3] as $size) {
            for ($index = 0; $index <= count($tokens) - $size; $index++) {
                $ngrams[] = array_slice($tokens, $index, $size);
            }
        }

        return $ngrams;
    }
}
