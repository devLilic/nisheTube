<?php

namespace Tests\Feature\Settings;

use App\Domain\YouTube\Enums\QuotaUsageOutcome;
use App\Models\ApiUsageEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class YouTubeIntegrationSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('youtube.api_key', 'test-secret-api-key');
        config()->set('youtube.max_attempts', 1);
        config()->set('youtube.retry_delay_milliseconds', 0);
    }

    public function test_authenticated_user_can_view_safe_integration_and_quota_settings(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('youtube.edit'));

        $response
            ->assertOk()
            ->assertDontSee('test-secret-api-key')
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/youtube')
                ->where('integration.provider', 'YouTube Data API v3')
                ->where('integration.keyConfigured', true)
                ->where('connectionResult', null)
                ->where('youtubeQuota.label', 'NisheTube estimate')
                ->has('youtubeQuota.buckets', 2));
    }

    public function test_key_presence_reports_missing_without_revealing_configuration(): void
    {
        config()->set('youtube.api_key', null);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('youtube.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('integration.keyConfigured', false));
    }

    public function test_connectivity_check_uses_provider_boundary_and_records_general_quota(): void
    {
        $user = User::factory()->create();

        Http::fake([
            'https://www.googleapis.com/youtube/v3/i18nRegions*' => Http::response([
                'items' => [
                    ['id' => 'US', 'snippet' => ['name' => 'United States']],
                ],
            ]),
        ]);

        $this->actingAs($user)
            ->post(route('youtube.test'))
            ->assertRedirect(route('youtube.edit'))
            ->assertSessionHas('youtube_connection', fn (array $result): bool => $result['status'] === 'success'
                && $result['title'] === 'Connection successful');

        Http::assertSent(fn (Request $request): bool => $request['key'] === 'test-secret-api-key'
            && $request['part'] === 'snippet'
            && $request['hl'] === 'en');

        $event = ApiUsageEvent::query()->sole();
        $this->assertSame($user->id, $event->user_id);
        $this->assertSame('general', $event->quota_bucket);
        $this->assertSame('i18nRegions.list', $event->endpoint);
        $this->assertSame(QuotaUsageOutcome::Succeeded, $event->outcome);
        $this->assertStringNotContainsString('test-secret-api-key', $event->toJson());
    }

    public function test_missing_key_connectivity_failure_is_safe_and_actionable(): void
    {
        config()->set('youtube.api_key', null);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('youtube.test'))
            ->assertRedirect(route('youtube.edit'))
            ->assertSessionHas('youtube_connection', fn (array $result): bool => $result['status'] === 'error'
                && str_contains($result['message'], 'YOUTUBE_API_KEY')
                && ! str_contains($result['message'], 'test-secret-api-key'));

        Http::assertNothingSent();
        $this->assertDatabaseCount('api_usage_events', 0);
    }

    public function test_invalid_key_connectivity_failure_never_exposes_the_key_or_upstream_message(): void
    {
        $user = User::factory()->create();

        Http::fake([
            'https://www.googleapis.com/youtube/v3/i18nRegions*' => Http::response([
                'error' => [
                    'errors' => [['reason' => 'keyInvalid']],
                    'message' => 'Rejected credential test-secret-api-key',
                ],
            ], 400),
        ]);

        $this->actingAs($user)
            ->post(route('youtube.test'))
            ->assertRedirect(route('youtube.edit'))
            ->assertSessionHas('youtube_connection', fn (array $result): bool => $result['status'] === 'error'
                && $result['message'] === 'Replace or correctly restrict the local API key, then try again.'
                && ! str_contains(json_encode($result, JSON_THROW_ON_ERROR), 'test-secret-api-key'));

        $this->get(route('youtube.edit'))
            ->assertDontSee('test-secret-api-key')
            ->assertDontSee('Rejected credential')
            ->assertInertia(fn (Assert $page) => $page
                ->where('integration.keyConfigured', true)
                ->where('connectionResult.status', 'error')
                ->where('connectionResult.message', 'Replace or correctly restrict the local API key, then try again.'));

        $event = ApiUsageEvent::query()->sole();
        $this->assertStringNotContainsString('test-secret-api-key', $event->toJson());
    }

    public function test_guests_cannot_view_or_test_the_integration(): void
    {
        $this->get(route('youtube.edit'))->assertRedirect(route('login'));
        $this->post(route('youtube.test'))->assertRedirect(route('login'));
    }
}
