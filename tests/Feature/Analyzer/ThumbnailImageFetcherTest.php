<?php

namespace Tests\Feature\Analyzer;

use App\Domain\Thumbnails\Exceptions\ThumbnailImageException;
use App\Domain\Thumbnails\Services\LaravelThumbnailImageFetcher;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class ThumbnailImageFetcherTest extends TestCase
{
    public function test_it_fetches_only_approved_https_images_with_bounded_supported_content(): void
    {
        Http::fake([
            'https://i.ytimg.com/*' => Http::response('image-bytes', 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $payload = (new LaravelThumbnailImageFetcher)->fetch('https://i.ytimg.com/vi/example/hqdefault.jpg');

        $this->assertSame('image/jpeg', $payload->mimeType);
        $this->assertSame(hash('sha256', 'image-bytes'), $payload->checksum);
        Http::assertSentCount(1);
    }

    #[DataProvider('rejectedUrls')]
    public function test_it_rejects_unapproved_or_insecure_hosts_without_a_network_request(string $url): void
    {
        Http::fake();

        try {
            (new LaravelThumbnailImageFetcher)->fetch($url);
            $this->fail('Expected a safe host-boundary exception.');
        } catch (ThumbnailImageException $exception) {
            $this->assertSame('thumbnail_host_not_allowed', $exception->errorCode);
        }

        Http::assertNothingSent();
    }

    /** @return array<string, array{string}> */
    public static function rejectedUrls(): array
    {
        return [
            'plain HTTP' => ['http://i.ytimg.com/vi/example/hqdefault.jpg'],
            'private address' => ['https://127.0.0.1/thumbnail.jpg'],
            'lookalike host' => ['https://i.ytimg.com.example.test/thumbnail.jpg'],
        ];
    }

    public function test_it_rejects_redirects_unsupported_types_and_oversized_images(): void
    {
        $cases = [
            ['https://i.ytimg.com/redirect.jpg', Http::response('', 302, ['Location' => 'https://example.test']), 'thumbnail_unavailable'],
            ['https://i.ytimg.com/text.jpg', Http::response('html', 200, ['Content-Type' => 'text/html']), 'thumbnail_invalid_type'],
            ['https://i.ytimg.com/large.jpg', Http::response(str_repeat('x', 65_537), 200, ['Content-Type' => 'image/jpeg']), 'thumbnail_invalid_size'],
        ];
        config(['thumbnails.max_bytes' => 65_536]);

        foreach ($cases as [$url, $response, $expectedCode]) {
            Http::fake([$url => $response]);
            try {
                (new LaravelThumbnailImageFetcher)->fetch($url);
                $this->fail("Expected {$expectedCode}.");
            } catch (ThumbnailImageException $exception) {
                $this->assertSame($expectedCode, $exception->errorCode);
            }
        }
    }
}
