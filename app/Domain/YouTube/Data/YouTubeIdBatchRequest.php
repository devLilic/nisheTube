<?php

namespace App\Domain\YouTube\Data;

use InvalidArgumentException;

final readonly class YouTubeIdBatchRequest
{
    /** @var list<string> */
    public array $ids;

    public ProviderRequestContext $context;

    /** @param list<string> $ids */
    public function __construct(array $ids, ?ProviderRequestContext $context = null)
    {
        $normalized = [];

        foreach ($ids as $id) {
            $id = trim($id);

            if ($id === '') {
                throw new InvalidArgumentException('YouTube identifiers cannot be blank.');
            }

            $normalized[$id] = $id;
        }

        $normalized = array_values($normalized);

        if ($normalized === [] || count($normalized) > 50) {
            throw new InvalidArgumentException('A YouTube enrichment request requires between 1 and 50 unique identifiers.');
        }

        $this->ids = $normalized;
        $this->context = $context ?? new ProviderRequestContext;
    }
}
