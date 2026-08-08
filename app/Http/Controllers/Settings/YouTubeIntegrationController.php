<?php

namespace App\Http\Controllers\Settings;

use App\Domain\YouTube\Actions\TestYouTubeConnection;
use App\Domain\YouTube\Enums\YouTubeErrorCode;
use App\Domain\YouTube\Exceptions\YouTubeProviderException;
use App\Domain\YouTube\Services\YouTubeConfiguration;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class YouTubeIntegrationController extends Controller
{
    public function edit(Request $request, YouTubeConfiguration $configuration): Response
    {
        return Inertia::render('settings/youtube', [
            'integration' => [
                'provider' => 'YouTube Data API v3',
                'keyConfigured' => $configuration->hasApiKey(),
            ],
            'connectionResult' => $request->session()->get('youtube_connection'),
        ]);
    }

    public function test(Request $request, TestYouTubeConnection $testConnection): RedirectResponse
    {
        $user = $request->user();
        Gate::authorize('update', $user);

        try {
            $testConnection->handle($user);

            $result = [
                'status' => 'success',
                'title' => 'Connection successful',
                'message' => 'NisheTube reached YouTube Data API v3 and recorded this quota attempt.',
                'checkedAt' => Date::now()->toIso8601String(),
            ];
            Inertia::flash('toast', ['type' => 'success', 'message' => __('YouTube connection verified.')]);
        } catch (YouTubeProviderException $exception) {
            $result = [
                'status' => 'error',
                'title' => 'Connection check failed',
                'message' => $this->guidanceFor($exception->providerCode),
                'checkedAt' => Date::now()->toIso8601String(),
            ];
            Inertia::flash('toast', ['type' => 'error', 'message' => __('YouTube connection check failed.')]);
        }

        return to_route('youtube.edit')->with('youtube_connection', $result);
    }

    private function guidanceFor(YouTubeErrorCode $code): string
    {
        return match ($code) {
            YouTubeErrorCode::KeyMissing => 'Add YOUTUBE_API_KEY to the local .env file, then try again.',
            YouTubeErrorCode::KeyInvalid => 'Replace or correctly restrict the local API key, then try again.',
            YouTubeErrorCode::ApiDisabled => 'Enable YouTube Data API v3 for the configured Google Cloud project.',
            YouTubeErrorCode::QuotaExhausted => 'The relevant quota bucket is exhausted. Wait for the Pacific Time reset.',
            YouTubeErrorCode::RateLimited => 'YouTube is temporarily rate-limiting requests. Try again later.',
            YouTubeErrorCode::RequestInvalid => 'The connectivity request was rejected. Review the local integration configuration.',
            YouTubeErrorCode::Unavailable => 'YouTube is temporarily unavailable. Try again later.',
            YouTubeErrorCode::PartialData => 'YouTube returned an incomplete response. Try the check again.',
        };
    }
}
