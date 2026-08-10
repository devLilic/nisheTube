<?php

namespace App\Domain\YouTube\Enums;

enum YouTubeErrorCode: string
{
    case KeyMissing = 'youtube_key_missing';
    case KeyInvalid = 'youtube_key_invalid';
    case ApiDisabled = 'youtube_api_disabled';
    case QuotaExhausted = 'youtube_quota_exhausted';
    case RateLimited = 'youtube_rate_limited';
    case RequestInvalid = 'youtube_request_invalid';
    case Unavailable = 'youtube_unavailable';
    case PartialData = 'youtube_partial_data';
    case VideoNotFound = 'youtube_video_not_found';
    case ChannelNotFound = 'youtube_channel_not_found';
    case PlaylistUnavailable = 'youtube_playlist_unavailable';
    case CommentsDisabled = 'youtube_comments_disabled';

    public function isRetryable(): bool
    {
        return match ($this) {
            self::RateLimited, self::Unavailable => true,
            default => false,
        };
    }

    public function safeMessage(): string
    {
        return match ($this) {
            self::KeyMissing => 'The YouTube API key is not configured.',
            self::KeyInvalid => 'The configured YouTube API key was rejected.',
            self::ApiDisabled => 'YouTube Data API v3 is not enabled for the configured project.',
            self::QuotaExhausted => 'The YouTube API quota bucket is exhausted.',
            self::RateLimited => 'YouTube temporarily rate-limited the request.',
            self::RequestInvalid => 'YouTube rejected the request parameters.',
            self::Unavailable => 'YouTube is temporarily unavailable.',
            self::PartialData => 'YouTube returned an incomplete response.',
            self::VideoNotFound => 'YouTube did not return this video. It may be unavailable, private, or removed.',
            self::ChannelNotFound => 'YouTube did not return this channel. It may be unavailable or removed.',
            self::PlaylistUnavailable => 'The channel uploads playlist is unavailable.',
            self::CommentsDisabled => 'Comments are disabled for this video.',
        };
    }
}
