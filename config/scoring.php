<?php

return [
    'default_version' => 'niche-opportunity-v2',

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
        'niche-opportunity-v2' => [
            'weights' => [
                'demand_momentum' => 0.25,
                'competition_opportunity' => 0.22,
                'audience_reachability' => 0.22,
                'content_freshness_gap' => 0.13,
                'creator_viability' => 0.18,
            ],
            'sample' => [
                'minimum' => 10,
                'target' => 25,
                'channel_target' => 12,
            ],
            'confidence_weights' => [
                'base_coverage' => 0.25,
                'sample_size' => 0.15,
                'strict_relevance' => 0.12,
                'stability' => 0.10,
                'channel_diversity' => 0.10,
                'subscriber_visibility' => 0.10,
                'format_classification' => 0.06,
                'outlier_independence' => 0.06,
                'independent_evidence' => 0.06,
            ],
            'normalization' => [
                'large_channel_subscribers' => 1000000,
                'small_channel_subscribers' => 10000,
                'mid_channel_subscribers' => 100000,
                'availability_warning_ratio' => 0.80,
                'partial_enrichment_ratio' => 0.95,
                'outlier_dependency_high_share' => 0.50,
                'outlier_dependency_medium_share' => 0.30,
            ],
        ],
    ],
];
