<?php

namespace App\Domain\Analyzer\ValueObjects;

use InvalidArgumentException;

final readonly class YouTubeVideoReference
{
    private const ID_PATTERN = '/^[A-Za-z0-9_-]{11}$/D';

    public function __construct(public string $videoId) {}

    public static function parse(string $input): self
    {
        $input = trim($input);

        if (preg_match(self::ID_PATTERN, $input) === 1) {
            return new self($input);
        }

        if ($input === '' || strlen($input) > 2048 || filter_var($input, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException('Enter a supported YouTube video URL or 11-character video ID.');
        }

        $parts = parse_url($input);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower(rtrim((string) ($parts['host'] ?? ''), '.'));
        $path = (string) ($parts['path'] ?? '');

        if (! in_array($scheme, ['http', 'https'], true) || isset($parts['user'], $parts['port'])) {
            throw new InvalidArgumentException('Enter a supported YouTube video URL or 11-character video ID.');
        }

        $videoId = match (true) {
            $host === 'youtu.be' => self::singlePathId($path),
            in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'music.youtube.com'], true) => self::youtubePathId($path, (string) ($parts['query'] ?? '')),
            default => null,
        };

        if ($videoId === null || preg_match(self::ID_PATTERN, $videoId) !== 1) {
            throw new InvalidArgumentException('Enter a supported YouTube video URL or 11-character video ID.');
        }

        return new self($videoId);
    }

    private static function singlePathId(string $path): ?string
    {
        $segments = array_values(array_filter(explode('/', trim($path, '/')), static fn (string $part): bool => $part !== ''));

        return count($segments) === 1 ? rawurldecode($segments[0]) : null;
    }

    private static function youtubePathId(string $path, string $query): ?string
    {
        $normalizedPath = rtrim($path, '/');

        if ($normalizedPath === '/watch') {
            preg_match_all('/(?:^|&)v=([^&]*)/', $query, $matches);

            return count($matches[1]) === 1 ? rawurldecode($matches[1][0]) : null;
        }

        if (preg_match('#^/(?:shorts|embed)/([^/]+)$#D', $normalizedPath, $matches) === 1) {
            return rawurldecode($matches[1]);
        }

        return null;
    }
}
