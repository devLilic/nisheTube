export type LibraryTargetType =
    'niche_candidate' | 'video' | 'channel' | 'research_query' | 'research_run';

export type LibraryProjectOption = {
    public_id: string;
    name: string;
    color: string | null;
};

export type LibraryTag = {
    public_id: string;
    name: string;
    color: string | null;
};

export type LibraryFavorite = {
    public_id: string;
    target_type: LibraryTargetType;
    target_reference: string;
    label: string;
    subtitle: string;
    href: string | null;
    note: string | null;
    project: LibraryProjectOption | null;
    tags: LibraryTag[];
    updated_at: string | null;
};

export type LibraryContext = {
    projects: LibraryProjectOption[];
    tags: LibraryTag[];
    favorites: LibraryFavorite[];
};

export type LibraryProject = {
    public_id: string;
    name: string;
    description: string | null;
    purpose: string | null;
    market_key: string | null;
    themes: string[];
    decision_status: 'exploring' | 'active' | 'decided' | 'paused';
    decision_note: string | null;
    color: string | null;
    archived: boolean;
    updated_at: string | null;
    query_count: number;
    favorite_count: number;
    discovery_count: number;
};
