<?php

return [
    'api_key' => env('YOUTUBE_API_KEY'),
    'base_url' => env('YOUTUBE_API_BASE_URL', 'https://www.googleapis.com/youtube/v3'),
    'timeout_seconds' => (int) env('YOUTUBE_HTTP_TIMEOUT', 15),
    'connect_timeout_seconds' => (int) env('YOUTUBE_CONNECT_TIMEOUT', 5),
    'max_attempts' => (int) env('YOUTUBE_MAX_ATTEMPTS', 3),
    'retry_delay_milliseconds' => (int) env('YOUTUBE_RETRY_DELAY_MS', 250),
    'quota_reset_timezone' => env('YOUTUBE_QUOTA_RESET_TIMEZONE', 'America/Los_Angeles'),

    'quota_buckets' => [
        'search' => [
            'allowance' => (int) env('YOUTUBE_SEARCH_DAILY_ALLOWANCE', 100),
        ],
        'general' => [
            'allowance' => (int) env('YOUTUBE_GENERAL_DAILY_ALLOWANCE', 10000),
        ],
    ],

    'endpoints' => [
        'search.list' => [
            'path' => 'search',
            'bucket' => 'search',
            'cost' => 1,
        ],
        'videos.list' => [
            'path' => 'videos',
            'bucket' => 'general',
            'cost' => 1,
        ],
        'channels.list' => [
            'path' => 'channels',
            'bucket' => 'general',
            'cost' => 1,
        ],
        'playlistItems.list' => [
            'path' => 'playlistItems',
            'bucket' => 'general',
            'cost' => 1,
        ],
        'commentThreads.list' => [
            'path' => 'commentThreads',
            'bucket' => 'general',
            'cost' => 1,
        ],
        'i18nRegions.list' => [
            'path' => 'i18nRegions',
            'bucket' => 'general',
            'cost' => 1,
        ],
    ],
];
