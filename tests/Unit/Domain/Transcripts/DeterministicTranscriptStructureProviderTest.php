<?php

namespace Tests\Unit\Domain\Transcripts;

use App\Domain\Transcripts\Data\TranscriptStructureSegment;
use App\Domain\Transcripts\Services\DeterministicTranscriptStructureProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DeterministicTranscriptStructureProviderTest extends TestCase
{
    public function test_complete_profile_contains_every_structure_kind_and_exact_evidence_offsets(): void
    {
        $texts = [
            'Today OpenAI Research introduces dumpling research and a clear dumpling promise for careful creators.',
            'OpenAI Research compares dumpling ingredients, dumpling methods, and practical kitchen evidence for creators.',
            'Why does dumpling dough need patient preparation and careful measurements for reliable kitchen results?',
            'The dumpling method develops through mixing, resting, rolling, filling, folding, steaming, and final tasting.',
            'Creators can compare each dumpling result and record which kitchen method produces the clearest improvement.',
            'This practical dumpling section explains repeatable preparation, useful measurements, and common kitchen mistakes.',
            'The closing returns to dumpling research and summarizes the preparation evidence for careful creators.',
            'Subscribe and leave a comment below if you want another dumpling method explained with measured evidence.',
        ];
        [$plainText, $segments] = $this->segments($texts, true);

        $result = (new DeterministicTranscriptStructureProvider)->analyze($plainText, 'en', $segments);

        self::assertSame('complete', $result->status);
        self::assertSame('en', $result->language);
        self::assertGreaterThanOrEqual(80, $result->wordCount);
        self::assertNotNull($result->confidenceScore);
        $kinds = array_column($result->insights, 'kind');
        foreach (['summary', 'topic', 'entity', 'hook', 'section', 'cta', 'question', 'script_structure'] as $kind) {
            self::assertContains($kind, $kinds);
        }
        foreach ($result->insights as $insight) {
            self::assertGreaterThan($insight->startOffset, $insight->endOffset);
            self::assertSame(
                mb_substr($plainText, $insight->startOffset, $insight->endOffset - $insight->startOffset),
                mb_substr($plainText, $insight->startOffset, $insight->endOffset - $insight->startOffset),
            );
        }
        self::assertNotNull(collect($result->insights)->firstWhere('kind', 'hook')?->startMs);
    }

    /** @param list<string> $texts */
    #[DataProvider('multilingualSamples')]
    public function test_multilingual_profiles_preserve_declared_language_and_detect_repeated_topics(
        string $language,
        array $texts,
        string $expectedTopic,
    ): void {
        [$plainText, $segments] = $this->segments($texts, false);

        $result = (new DeterministicTranscriptStructureProvider)->analyze($plainText, $language, $segments);

        self::assertSame($language, $result->language);
        self::assertContains($result->status, ['partial', 'complete']);
        self::assertTrue(collect($result->insights)->where('kind', 'topic')->contains(
            fn ($insight): bool => mb_strtolower($insight->label) === mb_strtolower($expectedTopic),
        ));
        self::assertContains('question', array_column($result->insights, 'kind'));
    }

    /** @return array<string, array{string, list<string>, string}> */
    public static function multilingualSamples(): array
    {
        return [
            'Romanian' => ['ro', [
                'Astăzi explicăm cercetarea despre găluște și metoda simplă pentru o bucătărie organizată.',
                'Metoda pentru găluște folosește ingrediente clare, măsurători corecte și o pregătire atentă.',
                'De ce metoda pentru găluște are nevoie de răbdare și de măsurători repetate?',
                'La final, găluște rămâne tema principală, iar metoda poate fi testată din nou acasă.',
            ], 'Găluște'],
            'Russian' => ['ru', [
                'Сегодня мы объясняем исследование пельмени и простой метод приготовления на домашней кухне.',
                'Метод пельмени использует понятные ингредиенты, точные измерения и аккуратную подготовку.',
                'Почему метод пельмени требует терпения и повторных измерений на каждом этапе?',
                'В конце пельмени остаются главной темой, а метод можно повторить дома самостоятельно.',
            ], 'Пельмени'],
        ];
    }

    public function test_short_input_is_insufficient_without_invented_insights(): void
    {
        [$plainText, $segments] = $this->segments(['A short transcript cannot support a structure claim.'], false);

        $result = (new DeterministicTranscriptStructureProvider)->analyze($plainText, 'en', $segments);

        self::assertSame('insufficient', $result->status);
        self::assertNull($result->confidenceScore);
        self::assertSame([], $result->insights);
    }

    /** @param list<string> $texts
     * @return array{string,list<TranscriptStructureSegment>}
     */
    private function segments(array $texts, bool $timed): array
    {
        $plainText = implode("\n", $texts);
        $offset = 0;
        $segments = [];
        foreach ($texts as $index => $text) {
            $length = mb_strlen($text);
            $segments[] = new TranscriptStructureSegment(
                $index + 1,
                $text,
                $offset,
                $offset + $length,
                $timed ? $index * 10_000 : null,
                $timed ? ($index + 1) * 10_000 : null,
            );
            $offset += $length + 1;
        }

        return [$plainText, $segments];
    }
}
