<?php

namespace App\Domain\YouTube\Services;

use App\Domain\YouTube\Enums\YouTubeErrorCode;
use App\Domain\YouTube\Exceptions\YouTubeProviderException;
use Illuminate\Http\Client\Response;

class YouTubeErrorMapper
{
    public function fromResponse(Response $response): YouTubeProviderException
    {
        $reason = $response->json('error.errors.0.reason');
        $reason = is_string($reason) ? strtolower($reason) : '';

        $code = match (true) {
            in_array($reason, ['keyinvalid', 'iprefererblocked', 'forbidden'], true) => YouTubeErrorCode::KeyInvalid,
            in_array($reason, ['accessnotconfigured', 'youtubesignuprequired'], true) => YouTubeErrorCode::ApiDisabled,
            in_array($reason, ['quotaexceeded', 'dailylimitexceeded'], true) => YouTubeErrorCode::QuotaExhausted,
            in_array($reason, ['ratelimitexceeded', 'userratelimitexceeded'], true),
            $response->status() === 429 => YouTubeErrorCode::RateLimited,
            in_array($reason, ['playlistnotfound', 'playlistoperationunsupported'], true) => YouTubeErrorCode::PlaylistUnavailable,
            $reason === 'commentsdisabled' => YouTubeErrorCode::CommentsDisabled,
            $reason === 'videonotfound' => YouTubeErrorCode::VideoNotFound,
            $response->status() === 400 => YouTubeErrorCode::RequestInvalid,
            in_array($response->status(), [401, 403], true) => YouTubeErrorCode::KeyInvalid,
            $response->serverError() => YouTubeErrorCode::Unavailable,
            default => YouTubeErrorCode::Unavailable,
        };

        return new YouTubeProviderException($code);
    }
}
