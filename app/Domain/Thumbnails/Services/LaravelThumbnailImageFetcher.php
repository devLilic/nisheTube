<?php

namespace App\Domain\Thumbnails\Services;

use App\Domain\Thumbnails\Contracts\ThumbnailImageFetcher;
use App\Domain\Thumbnails\Data\ThumbnailImagePayload;
use App\Domain\Thumbnails\Exceptions\ThumbnailImageException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

final class LaravelThumbnailImageFetcher implements ThumbnailImageFetcher
{
    public function fetch(string $url): ThumbnailImagePayload
    {
        $parts = parse_url($url);
        $scheme = is_array($parts) ? strtolower((string) ($parts['scheme'] ?? '')) : '';
        $host = is_array($parts) ? strtolower((string) ($parts['host'] ?? '')) : '';
        $allowedHosts = array_map('strtolower', (array) config('thumbnails.allowed_hosts', []));
        if ($scheme !== 'https' || $host === '' || ! in_array($host, $allowedHosts, true)) {
            throw new ThumbnailImageException('thumbnail_host_not_allowed', 'The stored thumbnail URL is not on an approved HTTPS image host.');
        }

        try {
            $response = Http::connectTimeout(5)
                ->timeout(15)
                ->withHeaders(['Accept' => 'image/jpeg,image/png,image/webp'])
                ->withOptions(['allow_redirects' => false])
                ->get($url);
        } catch (ConnectionException) {
            throw new ThumbnailImageException('thumbnail_unavailable', 'The thumbnail host could not be reached.');
        } catch (Throwable) {
            throw new ThumbnailImageException('thumbnail_fetch_failed', 'The thumbnail could not be fetched safely.');
        }

        if (! $response->successful()) {
            throw new ThumbnailImageException('thumbnail_unavailable', 'The thumbnail image is unavailable from its stored source.');
        }

        $mimeType = strtolower(trim(explode(';', (string) $response->header('Content-Type'))[0]));
        if (! in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw new ThumbnailImageException('thumbnail_invalid_type', 'The thumbnail response is not a supported image type.');
        }

        $bytes = $response->body();
        $maxBytes = max(65_536, min(5_242_880, (int) config('thumbnails.max_bytes', 2_097_152)));
        if ($bytes === '' || strlen($bytes) > $maxBytes) {
            throw new ThumbnailImageException('thumbnail_invalid_size', 'The thumbnail image is empty or exceeds the safe size limit.');
        }

        return new ThumbnailImagePayload($bytes, $mimeType, hash('sha256', $bytes));
    }
}
