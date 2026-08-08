<?php

return [
    'default_version' => 'niche-opportunity-v1',

    'versions' => [
        'niche-opportunity-v1' => [
            'weights' => [
                'demand_momentum' => 0.25,
                'competition_opportunity' => 0.20,
                'audience_reachability' => 0.20,
                'content_freshness_gap' => 0.15,
                'creator_viability' => 0.20,
            ],
            'sample' => [
                'minimum' => 10,
                'target' => 25,
                'channel_target' => 12,
            ],
            'normalization' => [
                'winsor_lower_percentile' => 10,
                'winsor_upper_percentile' => 90,
                'meaningful_views_per_day' => 100,
                'median_views_per_day_low' => 10,
                'median_views_per_day_high' => 10000,
                'upper_quartile_views_per_day_low' => 25,
                'upper_quartile_views_per_day_high' => 25000,
                'recency_half_life_days' => 180,
                'large_channel_subscribers' => 1000000,
                'reach_ratio_low' => 0.25,
                'reach_ratio_high' => 3.0,
                'recent_days' => 90,
                'stale_days' => 365,
                'freshness_gap_max_days' => 730,
                'mixed_format_minimum_each' => 2,
                'availability_warning_ratio' => 0.80,
                'partial_enrichment_ratio' => 0.95,
            ],
            'confidence_weights' => [
                'sample_size' => 0.30,
                'unique_channels' => 0.15,
                'enrichment' => 0.20,
                'subscriber_availability' => 0.10,
                'engagement_availability' => 0.075,
                'metadata_availability' => 0.075,
                'comparable_history' => 0.10,
            ],
        ],
    ],
];
