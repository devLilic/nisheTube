import type { MarketKey } from './research';

export type ExploreEntityType = 'video' | 'channel' | 'candidate';
export type ExploreSource =
    'all' | 'research' | 'analyzer' | 'discovery' | 'library';

export type ExploreFilters = {
    entity_type: ExploreEntityType;
    source: ExploreSource;
    market: MarketKey | null;
    category: string | null;
    topic: string | null;
    breakout: 'normal' | 'strong' | 'breakout' | null;
    channel_size: 'small' | 'mid' | 'large' | 'hidden' | null;
    min_performance: number | null;
    min_score: number | null;
    min_confidence: number | null;
    observed_from: string | null;
    observed_to: string | null;
    organization:
        'all' | 'favorite' | 'not_favorite' | 'curated' | 'unreviewed';
    sort: 'latest' | 'score_desc' | 'performance_desc' | 'title';
};

export type ExploreResult = {
    entity_type: ExploreEntityType;
    id: string;
    title: string;
    subtitle: string | null;
    thumbnail_url: string | null;
    youtube_url: string | null;
    market: MarketKey | null;
    category: { id: string; name: string | null } | null;
    observed_at: string | null;
    performance: number | null;
    breakout_class: string | null;
    subscriber_count: number | null;
    score: number | null;
    confidence: number | null;
    favorite: boolean;
    research_status: string | null;
    sources: string[];
    analyzer_url: string | null;
    validate_url: string | null;
    watchlist_public_id: string | null;
    partial: boolean;
    detected_topic_profile: {
        niche: string | null;
        language: string | null;
        version: string | null;
    } | null;
};
