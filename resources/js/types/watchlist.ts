export type WatchlistDelta = {
    video: {
        views: number | null;
        likes: number | null;
        comments: number | null;
    };
    channel: {
        subscribers: number | null;
        views: number | null;
        videos: number | null;
    };
};

export type WatchlistItem = {
    public_id: string;
    target_type: 'video' | 'channel';
    target_reference: string | null;
    label: string;
    thumbnail_url: string | null;
    youtube_url: string | null;
    analyzer_url: string | null;
    status: 'monitoring' | 'attention' | 'promising' | 'ruled_out';
    is_active: boolean;
    refresh_mode: 'manual';
    notification: {
        enabled: boolean;
        state: 'disabled' | 'waiting' | 'pending' | 'ready';
    };
    note: string | null;
    project: { public_id: string; name: string } | null;
    workspace: { public_id: string; name: string } | null;
    favorite: boolean;
    tags: { public_id: string; name: string; color: string | null }[];
    last_observed_at: string | null;
    last_refreshed_at: string | null;
    next_refresh_at: null;
    refresh: null | {
        public_id: string;
        status: 'queued' | 'processing' | 'completed' | 'partial' | 'failed';
        progress_percent: number;
        warnings: string[];
        error_code: string | null;
        error_message: string | null;
        deltas: WatchlistDelta | null;
        completed_at: string | null;
        is_active: boolean;
        quota_exhausted: boolean;
    };
};

export type WatchlistFilters = {
    search: string;
    type: 'all' | 'video' | 'channel';
    status: 'all' | WatchlistItem['status'];
    activity: 'all' | 'active' | 'paused';
    project: string;
};
