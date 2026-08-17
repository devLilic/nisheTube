<?php

namespace Tests\Feature\Navigation;

use App\Domain\Discovery\Enums\DiscoveryRunStatus;
use App\Domain\Discovery\Enums\NicheCandidateStatus;
use App\Domain\Research\Enums\ResearchRunKind;
use App\Domain\Research\Enums\ResearchRunStatus;
use App\Models\DiscoveryRun;
use App\Models\Market;
use App\Models\ResearchQuery;
use App\Models\ResearchRun;
use App\Models\User;
use Database\Seeders\MarketSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class GlobalResearchHeaderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MarketSeeder::class);
    }

    public function test_stored_search_is_validated_bounded_owner_scoped_and_provider_free(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $this->candidate($owner, 'Romanian woodworking ideas');
        $this->candidate($other, 'Romanian private competitor theme');
        $run = $this->researchRun($owner, 'Romanian woodworking validation');
        $this->researchRun($other, 'Romanian private validation');

        $this->actingAs($owner)
            ->getJson(route('global-research-search', ['q' => 'Romanian']))
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonFragment(['title' => 'Romanian woodworking ideas'])
            ->assertJsonFragment(['href' => '/research/runs/'.$run->public_id])
            ->assertJsonMissing(['title' => 'Romanian private competitor theme'])
            ->assertJsonMissing(['title' => 'Romanian private validation']);

        $this->getJson(route('global-research-search', ['q' => 'x']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('q');
        $this->assertDatabaseCount('api_usage_events', 0);
    }

    public function test_completed_run_notifications_are_owner_scoped_and_read_state_persists(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $owned = $this->researchRun($owner, 'Owned completed research', now()->subMinute());
        $this->researchRun($other, 'Foreign completed research', now());

        $this->actingAs($owner)->get(route('dashboard'))->assertOk()->assertInertia(
            fn (Assert $page): Assert => $page
                ->where('completedRunNotifications.unread_count', 1)
                ->where('completedRunNotifications.items.0.description', 'Owned completed research')
                ->where('completedRunNotifications.items.0.href', '/research/runs/'.$owned->public_id)
                ->where('completedRunNotifications.items.0.unread', true)
                ->missing('completedRunNotifications.items.1'),
        );

        $this->patch(route('completed-run-notifications.read'))->assertRedirect();
        $this->assertNotNull($owner->fresh()->completed_run_notifications_read_at);
        $this->get(route('dashboard'))->assertInertia(fn (Assert $page): Assert => $page
            ->where('completedRunNotifications.unread_count', 0)
            ->where('completedRunNotifications.items.0.unread', false));
        $this->assertDatabaseCount('api_usage_events', 0);
    }

    public function test_guest_cannot_search_or_change_notification_read_state(): void
    {
        $this->get(route('global-research-search', ['q' => 'theme']))->assertRedirect(route('login'));
        $this->patch(route('completed-run-notifications.read'))->assertRedirect(route('login'));
    }

    private function researchRun(User $user, string $queryText, $completedAt = null): ResearchRun
    {
        $market = Market::query()->where('key', 'global_en')->firstOrFail();
        $query = ResearchQuery::query()->create([
            'user_id' => $user->id,
            'market_id' => $market->id,
            'query_text' => $queryText,
            'query_key' => mb_strtolower($queryText),
        ]);

        return ResearchRun::query()->create([
            'user_id' => $user->id,
            'research_query_id' => $query->id,
            'kind' => ResearchRunKind::Search,
            'status' => ResearchRunStatus::Completed,
            'attempt_number' => 1,
            'query_text' => $queryText,
            'market_key' => $market->key,
            'region_code' => $market->region_code,
            'relevance_language' => $market->relevance_language,
            'parameters' => [],
            'requested_result_count' => 10,
            'completed_at' => $completedAt ?? now(),
        ]);
    }

    private function candidate(User $user, string $phrase): void
    {
        $market = Market::query()->where('key', 'global_en')->firstOrFail();
        $run = DiscoveryRun::query()->create([
            'user_id' => $user->id,
            'market_id' => $market->id,
            'status' => DiscoveryRunStatus::Completed,
            'market_key' => $market->key,
            'region_code' => $market->region_code,
            'relevance_language' => $market->relevance_language,
            'parameters' => [],
            'completed_at' => now(),
        ]);
        $run->candidates()->create([
            'phrase' => $phrase,
            'cluster_key' => sha1($phrase),
            'summary' => 'Stored theme evidence.',
            'evidence' => [],
            'status' => NicheCandidateStatus::New,
        ]);
    }
}
