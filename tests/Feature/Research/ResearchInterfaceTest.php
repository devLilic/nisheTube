<?php

namespace Tests\Feature\Research;

use App\Domain\Research\Actions\CreateResearchQuery;
use App\Domain\Research\Actions\CreateResearchRun;
use App\Domain\Research\Actions\TransitionResearchRun;
use App\Domain\Research\Data\RunFailure;
use App\Domain\Research\Enums\ResearchRunStatus;
use App\Jobs\Research\CollectResearchRunSearch;
use App\Models\Market;
use App\Models\ResearchRun;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\MarketSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ResearchInterfaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MarketSeeder::class);
    }

    public function test_search_pages_require_authentication_and_only_list_the_owners_recent_runs(): void
    {
        $owner = User::factory()->create([
            'default_market_key' => 'ro_ro',
            'default_result_depth' => 100,
        ]);
        $ownerRun = $this->newRun($owner);
        $foreignRun = $this->newRun(User::factory()->create());

        $this->get(route('research.create'))->assertRedirect(route('login'));
        $this->get(route('research.runs.show', $ownerRun))->assertRedirect(route('login'));

        $this->actingAs($owner)
            ->get(route('research.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('research/create')
                ->where('defaults.market_key', 'ro_ro')
                ->where('defaults.result_depth', 100)
                ->has('markets', 3)
                ->has('recent_runs', 1)
                ->where('recent_runs.0.public_id', $ownerRun->public_id)
                ->missing('recent_runs.1')
            );

        $this->actingAs($owner)
            ->get(route('research.runs.show', $foreignRun))
            ->assertForbidden();
    }

    public function test_user_can_create_a_queued_run_with_frozen_market_window_and_filters(): void
    {
        Queue::fake([CollectResearchRunSearch::class]);
        CarbonImmutable::setTestNow('2026-08-08 12:00:00 Europe/Chisinau');

        $user = User::factory()->create(['timezone' => 'Europe/Chisinau']);

        $response = $this->actingAs($user)->post(route('research.store'), [
            'query_text' => '  mobilier   pentru apartamente mici  ',
            'market_key' => 'ro_ro',
            'requested_result_count' => 100,
            'search_order' => 'viewCount',
            'published_window' => 'past_month',
            'video_duration' => 'medium',
            'video_category_id' => '26',
        ]);

        $run = ResearchRun::query()->sole();

        $response->assertRedirect(route('research.runs.show', $run));
        $this->assertSame($user->id, $run->user_id);
        $this->assertSame(ResearchRunStatus::Queued, $run->status);
        $this->assertSame('mobilier pentru apartamente mici', $run->query_text);
        $this->assertSame('ro_ro', $run->market_key);
        $this->assertSame('RO', $run->region_code);
        $this->assertSame('ro', $run->relevance_language);
        $this->assertSame(100, $run->requested_result_count);
        $this->assertSame('viewCount', $run->parameters['search_order']);
        $this->assertSame('medium', $run->parameters['video_duration']);
        $this->assertSame('26', $run->parameters['video_category_id']);
        $this->assertSame('2026-07-09T09:00:00+00:00', $run->parameters['published_after']);
        $this->assertSame('2026-08-08T09:00:00+00:00', $run->parameters['published_before']);
        Queue::assertPushed(CollectResearchRunSearch::class, fn (CollectResearchRunSearch $job): bool => $job->researchRunId === $run->id);

        CarbonImmutable::setTestNow();
    }

    public function test_search_creation_validates_enabled_markets_dates_depth_and_optional_filters(): void
    {
        $user = User::factory()->create();
        Market::query()->where('key', 'ru_ru')->update(['is_enabled' => false]);

        $this->actingAs($user)
            ->from(route('research.create'))
            ->post(route('research.store'), [
                'query_text' => '',
                'market_key' => 'ru_ru',
                'requested_result_count' => 500,
                'search_order' => 'popular',
                'published_window' => 'custom',
                'published_after' => '2026-08-10',
                'published_before' => '2026-08-01',
                'video_duration' => 'tiny',
                'video_category_id' => 'not-a-category',
            ])
            ->assertRedirect(route('research.create'))
            ->assertSessionHasErrors([
                'query_text',
                'market_key',
                'requested_result_count',
                'search_order',
                'published_before',
                'video_duration',
                'video_category_id',
            ]);

        $this->assertDatabaseCount('research_queries', 0);
        $this->assertDatabaseCount('research_runs', 0);
    }

    public function test_owner_can_view_real_partial_progress_and_safe_failure_guidance(): void
    {
        $owner = User::factory()->create();
        $run = $this->newRun($owner, 100);
        $run = app(TransitionResearchRun::class)->handle($run, ResearchRunStatus::Queued);
        $run = app(TransitionResearchRun::class)->handle($run, ResearchRunStatus::Searching);
        $run->update([
            'progress_percent' => 25,
            'collected_result_count' => 50,
            'collection_warnings' => ['YouTube returned incomplete data; 50 saved candidates remain useful.'],
        ]);
        $run->searchPages()->create([
            'page_number' => 1,
            'request_page_token' => null,
            'next_page_token' => 'safe-next-token',
            'result_count' => 50,
            'approximate_total_results' => 999999,
        ]);
        $run->searchResults()->create([
            'provider_video_id' => 'video-safe-1',
            'provider_channel_id' => 'channel-safe-1',
            'title' => 'Idei pentru un apartament mic',
            'published_at' => '2026-08-01 12:00:00',
            'result_rank' => 1,
            'page_number' => 1,
            'provider_order' => 1,
        ]);

        $this->actingAs($owner)
            ->get(route('research.runs.show', $run))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('research/show')
                ->where('run.status', 'searching')
                ->where('run.progress_percent', 25)
                ->where('run.collected_result_count', 50)
                ->where('run.collection_warnings.0', 'YouTube returned incomplete data; 50 saved candidates remain useful.')
                ->where('run.collection.pages_collected', 1)
                ->where('run.collection.sample_results.0.title', 'Idei pentru un apartament mic')
                ->where('run.is_active', true)
                ->where('run.error', null)
            );

        $failed = app(TransitionResearchRun::class)->handle(
            $run,
            ResearchRunStatus::Failed,
            new RunFailure('youtube_quota_exhausted', 'The YouTube API quota bucket is exhausted.'),
        );

        $this->actingAs($owner)
            ->get(route('research.runs.show', $failed))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('run.status', 'failed')
                ->where('run.is_active', false)
                ->where('run.can_retry', true)
                ->where('run.error.code', 'youtube_quota_exhausted')
                ->where('run.error.action', 'settings')
                ->where('run.error.title', 'Search quota is exhausted')
            );
    }

    public function test_owner_can_retry_a_failed_run_and_foreign_users_cannot_retry_it(): void
    {
        Queue::fake([CollectResearchRunSearch::class]);

        $owner = User::factory()->create();
        $failed = app(TransitionResearchRun::class)->handle(
            app(TransitionResearchRun::class)->handle($this->newRun($owner), ResearchRunStatus::Queued),
            ResearchRunStatus::Failed,
            new RunFailure('youtube_unavailable', 'YouTube is temporarily unavailable.'),
        );

        $this->actingAs(User::factory()->create())
            ->post(route('research.runs.retry', $failed))
            ->assertForbidden();

        $response = $this->actingAs($owner)->post(route('research.runs.retry', $failed));
        $retry = ResearchRun::query()->whereKeyNot($failed->id)->sole();

        $response->assertRedirect(route('research.runs.show', $retry));
        $this->assertSame(2, $retry->attempt_number);
        $this->assertSame(ResearchRunStatus::Queued, $retry->status);
        $this->assertSame($failed->parameters, $retry->parameters);
        Queue::assertPushed(CollectResearchRunSearch::class, fn (CollectResearchRunSearch $job): bool => $job->researchRunId === $retry->id);
    }

    private function newRun(User $user, int $requestedResultCount = 50): ResearchRun
    {
        $query = app(CreateResearchQuery::class)->handle(
            user: $user,
            market: Market::query()->where('key', 'global_en')->firstOrFail(),
            queryText: 'creator research',
        );

        return app(CreateResearchRun::class)->handle($user, $query, $requestedResultCount);
    }
}
