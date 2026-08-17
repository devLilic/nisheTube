<?php

namespace App\Domain\Research\Services;

final class ResearchIntakeCatalog
{
    /** @return list<array<string, int|string>> */
    public function validationPresets(): array
    {
        return [
            $this->preset('fast_scan', 'Fast scan', 25, 'past_month'),
            $this->preset('balanced', 'Balanced', 50, 'past_three_months'),
            $this->preset('deep_validation', 'Deep validation', 100, 'past_year'),
            $this->preset('trend_check', 'Trend check', 50, 'past_month', 'date'),
            $this->preset('emerging_trend', 'Emerging trend', 50, 'past_week', 'date'),
            $this->preset('evergreen_check', 'Evergreen check', 100, 'any', 'viewCount'),
            $this->preset('small_channel_opportunity', 'Small-channel opportunity', 50, 'past_three_months', targetChannelSize: 'small'),
            $this->preset('long_form_documentary', 'Long-form documentary', 50, 'past_year', videoDuration: 'long', contentFormat: 'long_form'),
            $this->preset('shorts_opportunity', 'Shorts opportunity', 50, 'past_month', 'date', 'short', 'shorts'),
        ];
    }

    /** @return array{search_request_cost: int, search_request_measure: string, max_results_per_request: int} */
    public function preflightConfiguration(): array
    {
        $endpoints = config('youtube.endpoints', []);
        $searchEndpoint = is_array($endpoints) && is_array($endpoints['search.list'] ?? null)
            ? $endpoints['search.list']
            : [];

        return [
            'search_request_cost' => (int) ($searchEndpoint['cost'] ?? 1),
            'search_request_measure' => (string) config('youtube.quota_buckets.search.measure', 'requests'),
            'max_results_per_request' => 50,
        ];
    }

    /** @return array<string, int|string> */
    private function preset(
        string $key,
        string $label,
        int $depth,
        string $window,
        string $order = 'relevance',
        string $videoDuration = 'any',
        string $contentFormat = 'any',
        string $targetChannelSize = 'any',
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'requested_result_count' => $depth,
            'published_window' => $window,
            'search_order' => $order,
            'video_duration' => $videoDuration,
            'content_format' => $contentFormat,
            'target_channel_size' => $targetChannelSize,
        ];
    }
}
