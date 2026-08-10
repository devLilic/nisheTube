<?php

namespace App\Domain\Collection\Data;

final readonly class CollectionObservationBatch
{
    /**
     * @param  array<string, CollectionObservation>  $observations
     * @param  array<string, CollectionChannelObservation>  $channelObservations
     */
    public function __construct(public array $observations, public array $channelObservations = []) {}

    public function observation(string $providerVideoId): ?CollectionObservation
    {
        return $this->observations[$providerVideoId] ?? null;
    }

    public function count(): int
    {
        return count($this->observations);
    }

    public function channelObservation(string $providerChannelId): ?CollectionChannelObservation
    {
        return $this->channelObservations[$providerChannelId] ?? null;
    }
}
