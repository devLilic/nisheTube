<?php

namespace Tests\Feature\Settings;

use App\Domain\Settings\Actions\FreezeMarketForRequest;
use App\Domain\YouTube\Data\VideoSearchRequest;
use App\Models\Market;
use Database\Seeders\MarketSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MarketStorageTest extends TestCase
{
    use RefreshDatabase;

    public function test_market_seeder_persists_the_three_canonical_markets_idempotently(): void
    {
        $this->seed(MarketSeeder::class);
        $this->seed(MarketSeeder::class);

        $this->assertDatabaseCount('markets', 3);
        $this->assertDatabaseHas('markets', [
            'key' => 'global_en',
            'name' => 'Global / English',
            'region_code' => null,
            'relevance_language' => 'en',
            'is_enabled' => true,
            'sort_order' => 10,
        ]);
        $this->assertDatabaseHas('markets', [
            'key' => 'ro_ro',
            'name' => 'Romania / Romanian',
            'region_code' => 'RO',
            'relevance_language' => 'ro',
            'is_enabled' => true,
            'sort_order' => 20,
        ]);
        $this->assertDatabaseHas('markets', [
            'key' => 'ru_ru',
            'name' => 'Russia / Russian',
            'region_code' => 'RU',
            'relevance_language' => 'ru',
            'is_enabled' => true,
            'sort_order' => 30,
        ]);
    }

    #[DataProvider('marketMappings')]
    public function test_seeded_markets_map_to_valid_frozen_provider_requests(
        string $key,
        ?string $expectedRegion,
        string $expectedLanguage,
        array $expectedParameters,
    ): void {
        $this->seed(MarketSeeder::class);

        $market = Market::query()->where('key', $key)->firstOrFail();
        $frozen = (new FreezeMarketForRequest)->handle($market);
        $request = VideoSearchRequest::forMarket('  creator research  ', $frozen, 25);

        $this->assertSame($key, $frozen->marketKey);
        $this->assertSame($expectedParameters, $frozen->youtubeRequestParameters());
        $this->assertSame('creator research', $request->query);
        $this->assertSame($expectedRegion, $request->regionCode);
        $this->assertSame($expectedLanguage, $request->relevanceLanguage);
        $this->assertSame(25, $request->maxResults);
    }

    public function test_frozen_market_parameters_do_not_change_when_market_storage_changes(): void
    {
        $this->seed(MarketSeeder::class);

        $market = Market::query()->where('key', 'ro_ro')->firstOrFail();
        $frozen = (new FreezeMarketForRequest)->handle($market);

        $market->update([
            'region_code' => 'MD',
            'relevance_language' => 'en',
        ]);

        $this->assertSame('RO', $frozen->regionCode);
        $this->assertSame('ro', $frozen->relevanceLanguage);
    }

    public function test_disabled_market_cannot_be_frozen_for_a_new_request(): void
    {
        $market = new Market([
            'key' => 'ro_ro',
            'name' => 'Romania / Romanian',
            'region_code' => 'RO',
            'relevance_language' => 'ro',
            'is_enabled' => false,
            'sort_order' => 20,
        ]);

        $this->expectException(DomainException::class);

        (new FreezeMarketForRequest)->handle($market);
    }

    /**
     * @return iterable<string, array{string, string|null, string, array<string, string>}>
     */
    public static function marketMappings(): iterable
    {
        yield 'global English omits region' => [
            'global_en',
            null,
            'en',
            ['relevanceLanguage' => 'en'],
        ];
        yield 'Romania Romanian' => [
            'ro_ro',
            'RO',
            'ro',
            ['relevanceLanguage' => 'ro', 'regionCode' => 'RO'],
        ];
        yield 'Russia Russian' => [
            'ru_ru',
            'RU',
            'ru',
            ['relevanceLanguage' => 'ru', 'regionCode' => 'RU'],
        ];
    }
}
