<?php

namespace Tests\Unit\Domain\Semantic;

use App\Domain\Semantic\Services\DeterministicEditorialTitlePatternProvider;
use PHPUnit\Framework\TestCase;

final class DeterministicEditorialTitlePatternProviderTest extends TestCase
{
    public function test_patterns_are_versioned_multilingual_and_deterministic(): void
    {
        $provider = new DeterministicEditorialTitlePatternProvider;

        $this->assertSame('editorial-title-patterns-v1', $provider->version());
        $this->assertSame(
            ['how_to', 'question', 'numbered_list'],
            array_map(fn ($pattern): string => $pattern->key, $provider->detect('How to organize 10 rooms?')),
        );
        $this->assertSame(['how_to'], array_map(fn ($pattern): string => $pattern->key, $provider->detect('Cum să organizezi apartamentul')));
        $this->assertSame(['question'], array_map(fn ($pattern): string => $pattern->key, $provider->detect('Почему это работает?')));
        $this->assertSame([], $provider->detect('Apartment storage makeover'));
    }
}
