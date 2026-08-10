<?php

namespace App\Domain\Analyzer\ValueObjects;

use InvalidArgumentException;

final readonly class YouTubeChannelReference
{
    private const CHANNEL_ID_PATTERN = '/^UC[A-Za-z0-9_-]{22}$/';

    private const ALLOWED_HOSTS = ['youtube.com', 'www.youtube.com', 'm.youtube.com'];

    public function __construct(public string $channelId) {}

    public static function parse(string $input): self
    {
        $input = trim($input);

        if (preg_match(self::CHANNEL_ID_PATTERN, $input) === 1) {
            return new self($input);
        }

        $url = parse_url($input);

        if (! is_array($url) || ! isset($url['scheme'], $url['host'], $url['path'])) {
            throw new InvalidArgumentException('Enter a YouTube channel URL or 24-character channel ID.');
        }

        if (! in_array(strtolower((string) $url['scheme']), ['http', 'https'], true)
            || ! in_array(strtolower((string) $url['host']), self::ALLOWED_HOSTS, true)
            || isset($url['user'])
            || isset($url['pass'])) {
            throw new InvalidArgumentException('Enter a canonical youtube.com channel URL.');
        }

        $segments = array_values(array_filter(explode('/', trim((string) $url['path'], '/'))));
        $channelId = count($segments) === 2 && $segments[0] === 'channel' ? $segments[1] : null;

        if (! is_string($channelId) || preg_match(self::CHANNEL_ID_PATTERN, $channelId) !== 1) {
            throw new InvalidArgumentException('Only /channel/ URLs are supported; handles require provider lookup and are not accepted here.');
        }

        return new self($channelId);
    }
}
