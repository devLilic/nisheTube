<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class HttpClientSafetyTest extends TestCase
{
    public function test_unfaked_external_requests_are_blocked(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('without a matching fake');

        Http::get('https://www.googleapis.com/youtube/v3/search');
    }

    public function test_explicit_http_fakes_can_return_sanitized_provider_fixtures(): void
    {
        Http::fake([
            'https://www.googleapis.com/youtube/v3/search*' => Http::response([
                'items' => [
                    ['id' => ['videoId' => 'fixture-video-id']],
                ],
            ]),
        ]);

        $response = Http::get('https://www.googleapis.com/youtube/v3/search', [
            'q' => 'fixture query',
        ]);

        $this->assertTrue($response->ok());
        $this->assertSame('fixture-video-id', $response->json('items.0.id.videoId'));

        Http::assertSentCount(1);
    }
}
