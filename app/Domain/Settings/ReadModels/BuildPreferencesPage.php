<?php

namespace App\Domain\Settings\ReadModels;

use App\Models\Market;
use DateTimeZone;

final class BuildPreferencesPage
{
    /** @return array{markets:list<array{value:string,label:string}>,timezones:list<string>,resultDepths:list<int>} */
    public function handle(): array
    {
        $markets = [];

        foreach (Market::query()
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['key', 'name']) as $market) {
            $markets[] = [
                'value' => $market->key,
                'label' => $market->name,
            ];
        }

        return [
            'markets' => $markets,
            'timezones' => DateTimeZone::listIdentifiers(),
            'resultDepths' => [25, 50, 100, 200],
        ];
    }
}
