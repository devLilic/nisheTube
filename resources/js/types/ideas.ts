export type SavedCommentIdea = {
    public_id: string;
    text: string;
    provider_comment_id: string;
    comment_published_at: string | null;
    saved_at: string | null;
    source_comment_available: boolean;
    video: {
        provider_video_id: string;
        title: string;
        thumbnail_url: string | null;
        youtube_url: string;
    };
};
