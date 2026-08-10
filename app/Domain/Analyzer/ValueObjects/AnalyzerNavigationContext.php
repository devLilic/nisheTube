<?php

namespace App\Domain\Analyzer\ValueObjects;

final readonly class AnalyzerNavigationContext
{
    private const ALLOWED_PATHS = [
        '/research/runs/',
        '/explore',
        '/discover',
        '/analyzer',
        '/watchlist',
        '/topics',
    ];

    private function __construct(public ?string $returnUrl) {}

    public static function fromInput(?string $value): self
    {
        if ($value === null || $value === '' || strlen($value) > 2048 || str_contains($value, '\\')) {
            return new self(null);
        }

        $parts = parse_url($value);
        if (! is_array($parts) || isset($parts['scheme']) || isset($parts['host']) || isset($parts['user']) || isset($parts['port']) || isset($parts['fragment'])) {
            return new self(null);
        }

        $path = $parts['path'] ?? '';
        if (! str_starts_with($path, '/') || str_starts_with($path, '//') || ! self::allowedPath($path)) {
            return new self(null);
        }

        $query = self::safeQuery($parts['query'] ?? null);

        return new self($path.($query === '' ? '' : '?'.$query));
    }

    /** @return array{return_url: string}|null */
    public function toArray(): ?array
    {
        return $this->returnUrl === null ? null : ['return_url' => $this->returnUrl];
    }

    private static function allowedPath(string $path): bool
    {
        foreach (self::ALLOWED_PATHS as $allowed) {
            $root = rtrim($allowed, '/');
            if ($path === $root || str_starts_with($path, $root.'/')) {
                return true;
            }
        }

        return false;
    }

    private static function safeQuery(?string $query): string
    {
        if ($query === null || $query === '') {
            return '';
        }

        parse_str($query, $values);
        $safe = [];
        foreach (array_slice($values, 0, 20, true) as $key => $value) {
            if (! is_string($key) || preg_match('/^[a-zA-Z0-9_\-]{1,64}$/', $key) !== 1 || ! is_scalar($value)) {
                continue;
            }
            $string = (string) $value;
            if (strlen($string) <= 255) {
                $safe[$key] = $string;
            }
        }

        return http_build_query($safe, '', '&', PHP_QUERY_RFC3986);
    }
}
