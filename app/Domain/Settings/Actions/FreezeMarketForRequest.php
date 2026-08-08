<?php

namespace App\Domain\Settings\Actions;

use App\Domain\Settings\Data\FrozenMarketParameters;
use App\Models\Market;
use DomainException;

class FreezeMarketForRequest
{
    public function handle(Market $market): FrozenMarketParameters
    {
        if (! $market->is_enabled) {
            throw new DomainException('The selected market is not enabled.');
        }

        return new FrozenMarketParameters(
            marketKey: $market->key,
            regionCode: $market->region_code,
            relevanceLanguage: $market->relevance_language,
        );
    }
}
