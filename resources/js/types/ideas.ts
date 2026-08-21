export type SavedCommentIdea = {
    public_id: string;
    text: string;
    provider_comment_id: string;
    comment_published_at: string | null;
    saved_at: string | null;
    source_comment_available: boolean;
    context: {
        decision_status: 'new' | 'reviewing' | 'selected' | 'ruled_out';
        format: string | null;
        audience: string | null;
        decision_note: string | null;
        workspace: {
            public_id: string;
            name: string;
            market_key: string;
        } | null;
        candidate: {
            public_id: string;
            phrase: string;
            market_key: string;
        } | null;
        workspace_available: boolean;
        candidate_available: boolean;
    };
    video: {
        provider_video_id: string;
        title: string;
        thumbnail_url: string | null;
        youtube_url: string;
    };
};

export type IdeaDecisionContextOptions = {
    workspaces: { public_id: string; name: string; market_key: string }[];
    candidates: { public_id: string; phrase: string; market_key: string }[];
};
