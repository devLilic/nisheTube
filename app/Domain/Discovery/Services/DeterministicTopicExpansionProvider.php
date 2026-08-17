<?php

namespace App\Domain\Discovery\Services;

use App\Domain\Discovery\Contracts\TopicExpansionProvider;
use App\Domain\Discovery\Data\TopicPhraseSignal;

class DeterministicTopicExpansionProvider implements TopicExpansionProvider
{
    private const PHRASE_LIMIT = 100;

    public function __construct(private readonly MultilingualPhraseNormalizer $normalizer) {}

    public function expand(array $signals): array
    {
        /** @var array<string, array{phrase: string, tokens: list<string>, videos: array<string, true>, seeds: array<string, true>, originals: array<string, true>, languages: array<string, true>, transformations: array<string, true>, strength: float}> $phrases */
        $phrases = [];

        foreach ($signals as $signal) {
            $normalized = $this->normalizer->normalize($signal->observation->title);
            $tokens = $normalized->tokens;

            foreach ($this->ngrams($tokens) as $ngram) {
                $phrase = implode(' ', $ngram);
                $key = $phrase;
                $phrases[$key] ??= [
                    'phrase' => $phrase,
                    'tokens' => $ngram,
                    'videos' => [],
                    'seeds' => [],
                    'originals' => [],
                    'languages' => [],
                    'transformations' => [],
                    'strength' => 0.0,
                ];
                $phrases[$key]['videos'][$signal->observation->providerVideoId] = true;
                $phrases[$key]['seeds'][$signal->observation->seedQuery] = true;
                $phrases[$key]['originals'][$normalized->original] = true;
                foreach ($normalized->languages as $language) {
                    $phrases[$key]['languages'][$language] = true;
                }
                foreach ($normalized->transformations as $transformation) {
                    $phrases[$key]['transformations'][$transformation] = true;
                }
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
                originalPhrases: array_keys($phrase['originals']),
                languages: array_keys($phrase['languages']),
                normalizationTransformations: array_keys($phrase['transformations']),
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
