<?php

namespace App\Domain\YouTube\Contracts;

use App\Domain\YouTube\Data\ProviderRequestContext;

interface YouTubeApiClient
{
    /**
     * @param  array<string, bool|int|string>  $query
     * @return array<string, mixed>
     */
    public function get(
        string $endpoint,
        array $query,
        ?ProviderRequestContext $context = null,
    ): array;
}
