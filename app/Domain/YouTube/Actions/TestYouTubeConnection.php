<?php

namespace App\Domain\YouTube\Actions;

use App\Domain\YouTube\Contracts\YouTubeApiClient;
use App\Domain\YouTube\Data\ProviderRequestContext;
use App\Models\User;

class TestYouTubeConnection
{
    public function __construct(private readonly YouTubeApiClient $client) {}

    public function handle(User $user): void
    {
        $this->client->get(
            endpoint: 'i18nRegions.list',
            query: ['part' => 'snippet', 'hl' => 'en'],
            context: new ProviderRequestContext(userId: $user->id),
        );
    }
}
