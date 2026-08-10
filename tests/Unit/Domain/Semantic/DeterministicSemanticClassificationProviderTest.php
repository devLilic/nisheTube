<?php

namespace Tests\Unit\Domain\Semantic;

use App\Domain\Semantic\Data\SemanticVideoInput;
use App\Domain\Semantic\Services\DeterministicSemanticClassificationProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DeterministicSemanticClassificationProviderTest extends TestCase
{
    public function test_repeated_titles_produce_deterministic_versioned_topics_and_concentration(): void
    {
        $provider = new DeterministicSemanticClassificationProvider;
        $inputs = [
            new SemanticVideoInput('video-1', 'Small apartment storage ideas'),
            new SemanticVideoInput('video-2', 'Small apartment storage makeover'),
            new SemanticVideoInput('video-3', 'Small apartment organization guide'),
            new SemanticVideoInput('video-4', 'Tiny kitchen storage guide'),
        ];

        $first = $provider->classify($inputs);
        $second = $provider->classify(array_reverse($inputs));

        $this->assertSame('semantic-title-terms-v1', $provider->version());
        $this->assertSame('complete', $first->status);
        $this->assertSame('en', $first->language);
        $this->assertNotNull($first->niche);
        $this->assertNotNull($second->niche);
        $this->assertSame('Small Apartment', $first->niche->label);
        $this->assertSame($first->niche->key, $second->niche->key);
        $this->assertSame($first->concentrationScore, $second->concentrationScore);
        $this->assertNotEmpty($first->topics);
        $this->assertNotEmpty($first->contentPillars);
        $this->assertContains('video-1', $first->niche->evidenceVideoIds);
    }

    /** @param list<string> $titles */
    #[DataProvider('multilingualTitles')]
    public function test_language_detection_preserves_multilingual_terms(string $expectedLanguage, array $titles): void
    {
        $provider = new DeterministicSemanticClassificationProvider;
        $result = $provider->classify(array_map(
            fn (string $title, int $index): SemanticVideoInput => new SemanticVideoInput("video-{$index}", $title),
            $titles,
            array_keys($titles),
        ));

        $this->assertSame($expectedLanguage, $result->language);
        $this->assertNotNull($result->niche);
        $this->assertNotSame('', $result->niche->label);
    }

    /** @return array<string, array{string, list<string>}> */
    public static function multilingualTitles(): array
    {
        return [
            'Romanian' => ['ro', ['Idei depozitare apartament mic', 'Depozitare apartament mic simplu', 'Organizare apartament mic acasă']],
            'Russian' => ['ru', ['Идеи хранения маленькой квартиры', 'Хранение маленькой квартиры дома', 'Организация маленькой квартиры просто']],
        ];
    }

    public function test_missing_input_is_an_honest_insufficient_result(): void
    {
        $result = (new DeterministicSemanticClassificationProvider)->classify([
            new SemanticVideoInput('only-video', 'Single title'),
        ]);

        $this->assertSame('insufficient', $result->status);
        $this->assertNull($result->niche);
        $this->assertNull($result->confidenceScore);
        $this->assertNotEmpty($result->warnings);
    }
}
