<?php

return [
    'freshness_window_seconds' => (int) env('ANALYZER_FRESHNESS_WINDOW_SECONDS', 21_600),
    'calculation_version' => 'video-profile-v1',
    'channel_calculation_version' => 'channel-baseline-v1',
    'recent_video_limit' => (int) env('ANALYZER_RECENT_VIDEO_LIMIT', 30),
    'relative_performance' => [
        'version' => 'video-relative-performance-v1',
        'minimum_baseline_count' => 3,
        'thresholds' => [
            'underperformer_max_exclusive' => 0.5,
            'normal_max_exclusive' => 1.5,
            'above_average_max_exclusive' => 3.0,
            'strong_max_inclusive' => 5.0,
        ],
    ],
    'channel_behavior' => [
        'version' => 'channel-behavior-v1',
        'momentum_block_size' => 5,
        'minimum_consistency_sample' => 5,
        'minimum_correlation_sample' => 5,
        'momentum_thresholds' => [
            'declining_max_exclusive' => 0.8,
            'stable_max_inclusive' => 1.2,
        ],
        'consistency_thresholds' => [
            'consistent_minimum' => 75.0,
            'mixed_minimum' => 50.0,
        ],
        'correlation_thresholds' => [
            'weak_max_exclusive' => 0.3,
            'moderate_max_exclusive' => 0.7,
        ],
        'history_limit' => 24,
    ],
    'semantic_performance' => [
        'version' => 'semantic-performance-v1',
        'minimum_sample_size' => 2,
        'title_pattern_version' => 'editorial-title-patterns-v1',
    ],
    'categories' => [
        '1' => 'Film & Animation',
        '2' => 'Autos & Vehicles',
        '10' => 'Music',
        '15' => 'Pets & Animals',
        '17' => 'Sports',
        '19' => 'Travel & Events',
        '20' => 'Gaming',
        '22' => 'People & Blogs',
        '23' => 'Comedy',
        '24' => 'Entertainment',
        '25' => 'News & Politics',
        '26' => 'Howto & Style',
        '27' => 'Education',
        '28' => 'Science & Technology',
        '29' => 'Nonprofits & Activism',
    ],
];
