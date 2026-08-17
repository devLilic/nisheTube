<?php

namespace App\Domain\YouTube\Services;

use App\Domain\YouTube\Data\QuotaEndpointDefinition;
use App\Domain\YouTube\Enums\YouTubeErrorCode;
use App\Domain\YouTube\Exceptions\YouTubeProviderException;
use DateTimeZone;
use InvalidArgumentException;

final readonly class YouTubeConfiguration
{
    /**
     * @param  array<string, int>  $bucketAllowances
     * @param  array<string, string>  $bucketMeasures
     * @param  array<string, QuotaEndpointDefinition>  $endpoints
     */
    public function __construct(
        private ?string $configuredApiKey,
        public string $baseUrl,
        public int $timeoutSeconds,
        public int $connectTimeoutSeconds,
        public int $maxAttempts,
        public int $retryDelayMilliseconds,
        public string $quotaResetTimezone,
        public array $bucketAllowances,
        public array $bucketMeasures,
        private array $endpoints,
    ) {}

    /** @param array<string, mixed> $config */
    public static function fromArray(array $config): self
    {
        $baseUrl = rtrim(self::requiredString($config, 'base_url'), '/');

        if (filter_var($baseUrl, FILTER_VALIDATE_URL) === false || parse_url($baseUrl, PHP_URL_SCHEME) !== 'https') {
            throw new InvalidArgumentException('The YouTube API base URL must be a valid HTTPS URL.');
        }

        $resetTimezone = self::requiredString($config, 'quota_reset_timezone');
        new DateTimeZone($resetTimezone);

        $buckets = self::requiredArray($config, 'quota_buckets');
        $bucketAllowances = [];
        $bucketMeasures = [];

        foreach ($buckets as $bucket => $definition) {
            if (! is_string($bucket) || ! is_array($definition)) {
                throw new InvalidArgumentException('YouTube quota bucket configuration is invalid.');
            }

            $allowance = $definition['allowance'] ?? null;
            $measure = $definition['measure'] ?? null;

            if (! is_int($allowance) || $allowance < 1) {
                throw new InvalidArgumentException("The [{$bucket}] quota allowance must be a positive integer.");
            }

            if (! in_array($measure, ['requests', 'units'], true)) {
                throw new InvalidArgumentException("The [{$bucket}] quota measure must be requests or units.");
            }

            $bucketAllowances[$bucket] = $allowance;
            $bucketMeasures[$bucket] = $measure;
        }

        $configuredEndpoints = self::requiredArray($config, 'endpoints');
        $endpoints = [];

        foreach ($configuredEndpoints as $name => $definition) {
            if (! is_string($name) || ! is_array($definition)) {
                throw new InvalidArgumentException('YouTube endpoint configuration is invalid.');
            }

            $path = $definition['path'] ?? null;
            $bucket = $definition['bucket'] ?? null;
            $cost = $definition['cost'] ?? null;

            if (! is_string($path) || ! is_string($bucket) || ! is_int($cost)) {
                throw new InvalidArgumentException("The [{$name}] endpoint configuration is invalid.");
            }

            if (! array_key_exists($bucket, $bucketAllowances)) {
                throw new InvalidArgumentException("The [{$name}] endpoint references an unknown quota bucket.");
            }

            $endpoints[$name] = new QuotaEndpointDefinition($name, $path, $bucket, $cost);
        }

        $apiKey = $config['api_key'] ?? null;
        $apiKey = is_string($apiKey) && trim($apiKey) !== '' ? trim($apiKey) : null;

        return new self(
            configuredApiKey: $apiKey,
            baseUrl: $baseUrl,
            timeoutSeconds: self::positiveInt($config, 'timeout_seconds'),
            connectTimeoutSeconds: self::positiveInt($config, 'connect_timeout_seconds'),
            maxAttempts: self::positiveInt($config, 'max_attempts'),
            retryDelayMilliseconds: self::nonNegativeInt($config, 'retry_delay_milliseconds'),
            quotaResetTimezone: $resetTimezone,
            bucketAllowances: $bucketAllowances,
            bucketMeasures: $bucketMeasures,
            endpoints: $endpoints,
        );
    }

    public function apiKey(): string
    {
        if ($this->configuredApiKey === null) {
            throw new YouTubeProviderException(YouTubeErrorCode::KeyMissing);
        }

        return $this->configuredApiKey;
    }

    public function hasApiKey(): bool
    {
        return $this->configuredApiKey !== null;
    }

    public function endpoint(string $name): QuotaEndpointDefinition
    {
        return $this->endpoints[$name]
            ?? throw new InvalidArgumentException("The [{$name}] YouTube endpoint is not configured.");
    }

    /** @param array<string, mixed> $config */
    private static function requiredString(array $config, string $key): string
    {
        $value = $config[$key] ?? null;

        if (! is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException("The [{$key}] YouTube configuration value is required.");
        }

        return trim($value);
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<mixed>
     */
    private static function requiredArray(array $config, string $key): array
    {
        $value = $config[$key] ?? null;

        if (! is_array($value) || $value === []) {
            throw new InvalidArgumentException("The [{$key}] YouTube configuration value is required.");
        }

        return $value;
    }

    /** @param array<string, mixed> $config */
    private static function positiveInt(array $config, string $key): int
    {
        $value = $config[$key] ?? null;

        if (! is_int($value) || $value < 1) {
            throw new InvalidArgumentException("The [{$key}] YouTube configuration value must be positive.");
        }

        return $value;
    }

    /** @param array<string, mixed> $config */
    private static function nonNegativeInt(array $config, string $key): int
    {
        $value = $config[$key] ?? null;

        if (! is_int($value) || $value < 0) {
            throw new InvalidArgumentException("The [{$key}] YouTube configuration value cannot be negative.");
        }

        return $value;
    }
}
