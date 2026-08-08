<?php

namespace Tests\Unit\Domain\Settings;

use App\Domain\Settings\Data\FrozenMarketParameters;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FrozenMarketParametersTest extends TestCase
{
    public function test_values_are_normalized_at_the_freezing_boundary(): void
    {
        $market = new FrozenMarketParameters(' RO_RO ', ' ro ', ' RO ');

        $this->assertSame('ro_ro', $market->marketKey);
        $this->assertSame('RO', $market->regionCode);
        $this->assertSame('ro', $market->relevanceLanguage);
    }

    /** @param array<string, string|null> $arguments */
    #[DataProvider('invalidParameters')]
    public function test_invalid_frozen_market_parameters_are_rejected(array $arguments): void
    {
        $this->expectException(InvalidArgumentException::class);

        new FrozenMarketParameters(...$arguments);
    }

    /**
     * @return iterable<string, array{array<string, string|null>}>
     */
    public static function invalidParameters(): iterable
    {
        yield 'blank key' => [[
            'marketKey' => '',
            'regionCode' => null,
            'relevanceLanguage' => 'en',
        ]];
        yield 'invalid region' => [[
            'marketKey' => 'ro_ro',
            'regionCode' => 'ROM',
            'relevanceLanguage' => 'ro',
        ]];
        yield 'invalid language' => [[
            'marketKey' => 'ro_ro',
            'regionCode' => 'RO',
            'relevanceLanguage' => 'romanian',
        ]];
    }
}
