<?php

namespace Database\Seeders;

use App\Domain\Settings\Enums\MarketKey;
use App\Models\Market;
use Illuminate\Database\Seeder;

class MarketSeeder extends Seeder
{
    public function run(): void
    {
        $timestamp = now();

        $markets = array_map(
            fn (MarketKey $market): array => [
                'key' => $market->value,
                'name' => $market->label(),
                'region_code' => $market->regionCode(),
                'relevance_language' => $market->relevanceLanguage(),
                'is_enabled' => true,
                'sort_order' => $market->sortOrder(),
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            MarketKey::cases(),
        );

        Market::query()->upsert(
            $markets,
            ['key'],
            ['name', 'region_code', 'relevance_language', 'is_enabled', 'sort_order', 'updated_at'],
        );
    }
}
