<?php

namespace App\Domain\Discovery\Services;

use App\Domain\Discovery\Data\NormalizedPhrase;
use Illuminate\Support\Str;

class MultilingualPhraseNormalizer
{
    public const VERSION = 'discovery-phrase-normalization-v1';

    private const STOP_WORDS = [
        'a', 'an', 'and', 'about', 'after', 'best', 'for', 'from', 'have', 'how', 'in', 'into', 'more', 'of', 'on', 'that', 'the', 'this', 'to', 'video', 'with',
        'care', 'cea', 'cele', 'cel', 'cum', 'de', 'din', 'este', 'la', 'o', 'pentru', 'si', 'și',
        'более', 'в', 'видео', 'для', 'и', 'как', 'на', 'о', 'с', 'это',
    ];

    /** @var array<string, string> */
    private const SYNONYMS = [
        'apartament' => 'apartment', 'apartamente' => 'apartment', 'apartamentul' => 'apartment',
        'flat' => 'apartment', 'flats' => 'apartment', 'квартира' => 'apartment', 'квартиры' => 'apartment',
        'kvartira' => 'apartment', 'kvartiry' => 'apartment',
        'depozitare' => 'storage', 'depozitarea' => 'storage', 'хранение' => 'storage', 'хранения' => 'storage',
        'hranenie' => 'storage', 'khranenie' => 'storage',
        'organizare' => 'organize', 'organizarea' => 'organize', 'organization' => 'organize', 'organizing' => 'organize',
        'организация' => 'organize', 'организации' => 'organize', 'organizatsiya' => 'organize',
        'mic' => 'small', 'mica' => 'small', 'mică' => 'small', 'mici' => 'small', 'маленькая' => 'small',
        'маленький' => 'small', 'маленькие' => 'small', 'malenkaya' => 'small', 'malenkiy' => 'small',
        'ghid' => 'guide', 'tutorial' => 'guide', 'руководство' => 'guide', 'gid' => 'guide',
        'idei' => 'idea', 'ideas' => 'idea', 'идеи' => 'idea', 'ideĭ' => 'idea',
    ];

    public function normalize(string $value): NormalizedPhrase
    {
        $original = Str::squish($value);
        $lower = Str::lower($original);
        $rawTokens = preg_split('/[^\p{L}\p{N}]+/u', $lower, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $tokens = [];
        $languages = [];
        $transformations = [];

        foreach ($rawTokens as $rawToken) {
            $language = preg_match('/\p{Cyrillic}/u', $rawToken) === 1 ? 'ru' : $this->latinLanguage($rawToken);
            $languages[$language] = true;

            if (mb_strlen($rawToken) < 2 || in_array($rawToken, self::STOP_WORDS, true)) {
                $transformations['stop_words_removed'] = true;

                continue;
            }

            $token = self::SYNONYMS[$rawToken] ?? $this->stem($rawToken, $language);

            if ($token !== $rawToken) {
                $transformations[array_key_exists($rawToken, self::SYNONYMS) ? 'synonyms_or_transliterations' : 'inflections'] = true;
            }

            if (mb_strlen($token) >= 2 && ! in_array($token, $tokens, true)) {
                $tokens[] = $token;
            }
        }

        return new NormalizedPhrase(
            original: $original,
            key: implode(' ', $tokens),
            tokens: $tokens,
            languages: array_keys($languages),
            transformations: array_keys($transformations),
        );
    }

    private function latinLanguage(string $token): string
    {
        if (preg_match('/[ăâîșşțţ]/u', $token) === 1 || preg_match('/(are|ului|elor|ilor|uri)$/u', $token) === 1) {
            return 'ro';
        }

        return 'en';
    }

    private function stem(string $token, string $language): string
    {
        $suffixes = match ($language) {
            'ru' => ['иями', 'ами', 'ого', 'ему', 'ение', 'ения', 'ами', 'ями', 'ов', 'ах', 'ы', 'и', 'а', 'я'],
            'ro' => ['urilor', 'ului', 'elor', 'ilor', 'uri', 'ele', 'ile', 'ul', 'ea', 'e', 'i'],
            default => ['ing', 'ies', 'ed', 'es', 's'],
        };

        foreach ($suffixes as $suffix) {
            if (mb_strlen($token) - mb_strlen($suffix) >= 4 && str_ends_with($token, $suffix)) {
                return mb_substr($token, 0, mb_strlen($token) - mb_strlen($suffix));
            }
        }

        return $token;
    }
}
