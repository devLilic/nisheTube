export const externalYouTubeLinkProps = {
    target: '_blank',
    rel: 'noopener noreferrer',
} as const;

export function youtubeVideoUrl(providerVideoId: string): string {
    return `https://www.youtube.com/watch?v=${encodeURIComponent(providerVideoId)}`;
}

export function youtubeChannelUrl(providerChannelId: string): string {
    return `https://www.youtube.com/channel/${encodeURIComponent(providerChannelId)}`;
}

export function videoPreviewAvailable(
    thumbnailUrl: string | null,
    failed: boolean,
): boolean {
    return thumbnailUrl !== null && !failed;
}
