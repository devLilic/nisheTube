<?php

namespace App\Domain\YouTube\Data;

use App\Domain\Settings\Data\FrozenMarketParameters;
use InvalidArgumentException;

final readonly class VideoSearchRequest
{
    public string $query;

    public string $relevanceLanguage;

    public ?string $regionCode;

    public int $maxResults;

    public ?string $pageToken;

    public ProviderRequestContext $context;

    public ?string $order;

    public ?string $publishedAfter;

    public ?string $publishedBefore;

    public ?string $videoDuration;

    public ?string $videoCategoryId;

    public function __construct(
        string $query,
        string $relevanceLanguage,
        ?string $regionCode = null,
        int $maxResults = 50,
        ?string $pageToken = null,
        ?ProviderRequestContext $context = null,
        ?string $order = null,
        ?string $publishedAfter = null,
        ?string $publishedBefore = null,
        ?string $videoDuration = null,
        ?string $videoCategoryId = null,
    ) {
        $query = trim($query);
        $relevanceLanguage = strtolower(trim($relevanceLanguage));
        $regionCode = self::nullableTrim($regionCode);
        $pageToken = self::nullableTrim($pageToken);
        $order = self::nullableTrim($order);
        $publishedAfter = self::nullableTrim($publishedAfter);
        $publishedBefore = self::nullableTrim($publishedBefore);
        $videoDuration = self::nullableTrim($videoDuration);
        $videoCategoryId = self::nullableTrim($videoCategoryId);

        if ($query === '') {
            throw new InvalidArgumentException('A search query is required.');
        }

        if (preg_match('/^[a-z]{2,3}$/', $relevanceLanguage) !== 1) {
            throw new InvalidArgumentException('The relevance language must be a two or three letter language code.');
        }

        if ($regionCode !== null && preg_match('/^[A-Za-z]{2}$/', $regionCode) !== 1) {
            throw new InvalidArgumentException('The region code must be a two letter country code.');
        }

        if ($maxResults < 1 || $maxResults > 50) {
            throw new InvalidArgumentException('The provider page size must be between 1 and 50.');
        }

        if ($order !== null && ! in_array($order, ['date', 'rating', 'relevance', 'title', 'viewCount'], true)) {
            throw new InvalidArgumentException('The provider search order is invalid.');
        }

        if ($videoDuration !== null && ! in_array($videoDuration, ['any', 'short', 'medium', 'long'], true)) {
            throw new InvalidArgumentException('The provider video duration is invalid.');
        }

        $this->query = $query;
        $this->relevanceLanguage = $relevanceLanguage;
        $this->regionCode = $regionCode === null ? null : strtoupper($regionCode);
        $this->maxResults = $maxResults;
        $this->pageToken = $pageToken;
        $this->context = $context ?? new ProviderRequestContext;
        $this->order = $order;
        $this->publishedAfter = $publishedAfter;
        $this->publishedBefore = $publishedBefore;
        $this->videoDuration = $videoDuration;
        $this->videoCategoryId = $videoCategoryId;
    }

    public static function forMarket(
        string $query,
        FrozenMarketParameters $market,
        int $maxResults = 50,
        ?string $pageToken = null,
        ?ProviderRequestContext $context = null,
    ): self {
        return new self(
            query: $query,
            relevanceLanguage: $market->relevanceLanguage,
            regionCode: $market->regionCode,
            maxResults: $maxResults,
            pageToken: $pageToken,
            context: $context,
        );
    }

    public function withPageToken(?string $pageToken): self
    {
        return new self(
            query: $this->query,
            relevanceLanguage: $this->relevanceLanguage,
            regionCode: $this->regionCode,
            maxResults: $this->maxResults,
            pageToken: $pageToken,
            context: $this->context,
            order: $this->order,
            publishedAfter: $this->publishedAfter,
            publishedBefore: $this->publishedBefore,
            videoDuration: $this->videoDuration,
            videoCategoryId: $this->videoCategoryId,
        );
    }

    private static function nullableTrim(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
