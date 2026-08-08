<?php

namespace Tests\Feature\Discovery;

use App\Domain\Discovery\Actions\CreateDiscoveryRun;
use App\Domain\Discovery\Actions\LinkDiscoverySeedResearchRun;
use App\Domain\Discovery\Enums\DiscoveryRunStatus;
use App\Domain\Discovery\Enums\NicheCandidateStatus;
use App\Domain\Research\Actions\CreateResearchQuery;
use App\Domain\Research\Actions\CreateResearchRun;
use App\Domain\Research\Enums\ResearchRunKind;
use App\Domain\Research\Enums\ResearchRunStatus;
use App\Domain\YouTube\Enums\QuotaUsageOutcome;
use App\Jobs\Discovery\GenerateDiscoveryCandidates;
use App\Jobs\Research\CollectResearchRunSearch;
use App\Models\ApiUsageEvent;
use App\Models\Channel;
use App\Models\DiscoveryRun;
use App\Models\Market;
use App\Models\NicheCandidate;
use App\Models\ResearchRun;
use App\Models\User;
use App\Models\Video;
use Carbon\CarbonImmutable;
use Database\Seeders\MarketSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DiscoveryInterfaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MarketSeeder::class);
        Queue::fake();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_discovery_entry_lists_only_the_owners_completed_samples_and_runs(): void
    {
        $owner = User::factory()->create(['default_market_key' => 'ro_ro']);
        $foreignUser = User::factory()->create();
        $market = Market::query()->where('key', 'ro_ro')->firstOrFail();
        $ownerSample = $this->completedSample($owner, $market, 'organizare apartament');
        $foreignSample = $this->completedSample($foreignUser, $market, 'foreign private sample');
        $ownerDiscovery = app(CreateDiscoveryRun::class)->handle($owner, $market, ['organizare apartament']);

        $this->get(route('discovery.index'))->assertRedirect(route('login'));

        $this->actingAs($owner)
            ->get(route('discovery.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('discovery/index')
                ->where('default_market_key', 'ro_ro')
                ->has('markets', 3)
                ->has('sample_runs', 1)
                ->where('sample_runs.0.public_id', $ownerSample->public_id)
                ->where('sample_runs.0.video_count', 1)
                ->has('recent_runs', 1)
                ->where('recent_runs.0.public_id', $ownerDiscovery->public_id)
            )
            ->assertDontSee($foreignSample->public_id);
    }

    public function test_valid_submission_links_samples_and_queues_the_discovery_run(): void
    {
        $owner = User::factory()->create();
        $market = Market::query()->where('key', 'global_en')->firstOrFail();
        $sample = $this->completedSample($owner, $market, 'compact living');

        $response = $this->actingAs($owner)->post(route('discovery.store'), [
            'market_key' => 'global_en',
            'sample_per_seed' => 10,
            'candidate_limit' => 10,
            'seeds' => [[
                'query' => 'compact apartment storage',
                'research_run_id' => $sample->public_id,
            ]],
        ]);

        $run = DiscoveryRun::query()->sole();
        $response->assertRedirect(route('discovery.runs.show', $run));
        $this->assertSame(DiscoveryRunStatus::Queued, $run->status);
        $this->assertSame(10, $run->parameters['sample_per_seed']);
        $this->assertSame(10, $run->parameters['candidate_limit']);
        $this->assertSame($sample->id, $run->seeds()->sole()->research_run_id);
        Queue::assertPushed(GenerateDiscoveryCandidates::class);
    }

    public function test_submission_rejects_invalid_budgets_duplicate_seeds_and_foreign_samples(): void
    {
        $owner = User::factory()->create();
        $foreignUser = User::factory()->create();
        $market = Market::query()->where('key', 'global_en')->firstOrFail();
        $foreignSample = $this->completedSample($foreignUser, $market, 'private sample');

        $this->actingAs($owner)
            ->post(route('discovery.store'), [
                'market_key' => 'global_en',
                'sample_per_seed' => 12,
                'candidate_limit' => 50,
                'seeds' => [
                    ['query' => 'Same seed', 'research_run_id' => $foreignSample->public_id],
                    ['query' => 'same seed', 'research_run_id' => $foreignSample->public_id],
                ],
            ])
            ->assertSessionHasErrors([
                'sample_per_seed',
                'candidate_limit',
                'seeds.1.query',
                'seeds.1.research_run_id',
            ]);

        $this->actingAs($owner)
            ->post(route('discovery.store'), [
                'market_key' => 'global_en',
                'sample_per_seed' => 25,
                'candidate_limit' => 20,
                'seeds' => [[
                    'query' => 'private sample',
                    'research_run_id' => $foreignSample->public_id,
                ]],
            ])
            ->assertNotFound();
        $this->assertDatabaseCount('discovery_runs', 0);
    }

    public function test_run_detail_is_owner_scoped_and_exposes_progress_evidence_and_empty_results(): void
    {
        $owner = User::factory()->create();
        $foreignUser = User::factory()->create();
        $market = Market::query()->where('key', 'global_en')->firstOrFail();
        $run = app(CreateDiscoveryRun::class)->handle($owner, $market, ['small homes']);
        $candidate = $run->candidates()->create([
            'phrase' => 'small apartment storage',
            'cluster_key' => 'cluster-one',
            'summary' => 'Observed across two breakout videos.',
            'evidence' => [
                'observed_signal' => 'returned_video_breakout',
                'video_ids' => ['video-one', 'video-two'],
                'seed_queries' => ['small homes'],
                'source_video_count' => 2,
                'seed_count' => 1,
            ],
            'overall_score' => 78.5,
            'confidence_score' => 65,
            'formula_version' => 'discovery-breakout-v1',
            'status' => NicheCandidateStatus::New,
        ]);
        $run->update([
            'status' => DiscoveryRunStatus::Completed,
            'progress_percent' => 100,
            'candidate_count' => 1,
            'completed_at' => now(),
        ]);

        $this->actingAs($foreignUser)
            ->get(route('discovery.runs.show', $run))
            ->assertForbidden();

        $this->actingAs($owner)
            ->get(route('discovery.runs.show', $run))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('discovery/show')
                ->where('run.status', 'completed')
                ->where('run.progress_percent', 100)
                ->has('run.candidates', 1)
                ->where('run.candidates.0.public_id', $candidate->public_id)
                ->where('run.candidates.0.evidence.observed_signal', 'returned_video_breakout')
                ->where('run.candidates.0.evidence.video_ids.0', 'video-one')
            );

        $empty = app(CreateDiscoveryRun::class)->handle($owner, $market, ['steady topic']);
        $empty->update([
            'status' => DiscoveryRunStatus::Completed,
            'progress_percent' => 100,
            'completed_at' => now(),
        ]);
        $this->actingAs($owner)
            ->get(route('discovery.runs.show', $empty))
            ->assertInertia(fn (Assert $page): Assert => $page->has('run.candidates', 0));
    }

    public function test_owner_can_save_dismiss_and_launch_one_validation_search(): void
    {
        $owner = User::factory()->create();
        $foreignUser = User::factory()->create();
        $market = Market::query()->where('key', 'global_en')->firstOrFail();
        $run = app(CreateDiscoveryRun::class)->handle($owner, $market, ['compact homes']);
        $candidate = $this->candidate($run);

        $this->actingAs($foreignUser)
            ->patch(route('discovery.candidates.update', $candidate), ['status' => 'saved'])
            ->assertForbidden();

        $this->actingAs($owner)
            ->patch(route('discovery.candidates.update', $candidate), ['status' => 'saved'])
            ->assertRedirect();
        $this->assertSame(NicheCandidateStatus::Saved, $candidate->fresh()->status);

        $this->actingAs($owner)
            ->patch(route('discovery.candidates.update', $candidate), ['status' => 'dismissed'])
            ->assertRedirect();
        $this->assertSame(NicheCandidateStatus::Dismissed, $candidate->fresh()->status);

        $this->actingAs($foreignUser)
            ->post(
                route('discovery.candidates.validate', $candidate),
                ['requested_result_count' => 50],
            )
            ->assertForbidden();
        $this->assertDatabaseCount('research_runs', 0);

        $response = $this->actingAs($owner)->post(
            route('discovery.candidates.validate', $candidate),
            ['requested_result_count' => 50],
        );
        $validation = ResearchRun::query()->where('kind', ResearchRunKind::DiscoveryValidation)->sole();

        $response->assertRedirect(route('research.runs.show', $validation));
        $this->assertSame(ResearchRunStatus::Queued, $validation->status);
        $this->assertSame($candidate->phrase, $validation->query_text);
        $this->assertSame($validation->id, $candidate->fresh()->validation_research_run_id);
        $this->assertSame(NicheCandidateStatus::Validated, $candidate->fresh()->status);
        Queue::assertPushed(CollectResearchRunSearch::class);

        $this->actingAs($owner)
            ->post(route('discovery.candidates.validate', $candidate), ['requested_result_count' => 50])
            ->assertSessionHasErrors('candidate');
        $this->assertDatabaseCount('research_runs', 1);
    }

    public function test_failed_discovery_retry_is_owner_scoped_and_clears_safe_failure_state(): void
    {
        $owner = User::factory()->create();
        $foreignUser = User::factory()->create();
        $market = Market::query()->where('key', 'global_en')->firstOrFail();
        $sample = $this->completedSample($owner, $market, 'retryable sample');
        $run = app(CreateDiscoveryRun::class)->handle($owner, $market, ['retryable discovery']);
        app(LinkDiscoverySeedResearchRun::class)->handle($owner, $run->seeds->firstOrFail(), $sample);
        $run->update([
            'status' => DiscoveryRunStatus::Failed,
            'progress_percent' => 25,
            'failed_at' => now(),
            'error_code' => 'discovery_generation_failed',
            'error_message' => 'Safe retry guidance.',
        ]);

        $this->actingAs($foreignUser)
            ->post(route('discovery.runs.retry', $run))
            ->assertForbidden();
        $this->assertSame(DiscoveryRunStatus::Failed, $run->fresh()->status);

        $this->actingAs($owner)
            ->post(route('discovery.runs.retry', $run))
            ->assertRedirect(route('discovery.runs.show', $run));

        $retried = $run->fresh();
        $this->assertSame(DiscoveryRunStatus::Queued, $retried->status);
        $this->assertSame(5, $retried->progress_percent);
        $this->assertNull($retried->failed_at);
        $this->assertNull($retried->error_code);
        $this->assertNull($retried->error_message);
        Queue::assertPushed(
            GenerateDiscoveryCandidates::class,
            fn (GenerateDiscoveryCandidates $job): bool => $job->discoveryRunId === $run->id,
        );
    }

    public function test_exhausted_search_estimate_is_exposed_while_stored_discovery_still_queues_without_usage(): void
    {
        CarbonImmutable::setTestNow('2026-08-08 12:00:00 UTC');
        config()->set('youtube.quota_buckets.search.allowance', 1);
        $owner = User::factory()->create();
        $market = Market::query()->where('key', 'global_en')->firstOrFail();
        $sample = $this->completedSample($owner, $market, 'quota-safe sample');
        ApiUsageEvent::query()->create([
            'user_id' => $owner->id,
            'provider' => 'youtube',
            'quota_bucket' => 'search',
            'endpoint' => 'search.list',
            'request_count' => 1,
            'estimated_cost' => 1,
            'outcome' => QuotaUsageOutcome::Succeeded,
            'occurred_at' => now(),
        ]);

        $this->actingAs($owner)
            ->post(route('discovery.store'), [
                'market_key' => 'global_en',
                'sample_per_seed' => 25,
                'candidate_limit' => 20,
                'seeds' => [[
                    'query' => 'quota-safe discovery',
                    'research_run_id' => $sample->public_id,
                ]],
            ])
            ->assertRedirect();

        $run = DiscoveryRun::query()->sole();
        $this->assertSame(DiscoveryRunStatus::Queued, $run->status);
        $this->assertDatabaseCount('api_usage_events', 1);

        $this->actingAs($owner)
            ->get(route('discovery.runs.show', $run))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('youtubeQuota.buckets.0.bucket', 'search')
                ->where('youtubeQuota.buckets.0.remaining', 0)
                ->where('youtubeQuota.buckets.0.exhausted', true)
                ->where('run.status', 'queued')
            );
    }

    private function completedSample(User $user, Market $market, string $queryText): ResearchRun
    {
        $query = app(CreateResearchQuery::class)->handle($user, $market, $queryText);
        $run = app(CreateResearchRun::class)->handle($user, $query, 25);
        $channel = Channel::query()->create([
            'provider' => 'youtube',
            'provider_channel_id' => 'channel-'.$run->id,
            'title' => 'Sample channel',
        ]);
        $video = Video::query()->create([
            'provider' => 'youtube',
            'provider_video_id' => 'video-'.$run->id,
            'channel_id' => $channel->id,
            'title' => $queryText,
            'published_at' => now()->subDays(4),
        ]);
        $run->videos()->attach($video->id, [
            'result_rank' => 1,
            'page_number' => 1,
            'provider_order' => 1,
        ]);
        $run->videoSnapshots()->create([
            'video_id' => $video->id,
            'view_count' => 1000,
            'views_per_day' => 250,
            'views_to_subscribers_ratio' => 2.5,
            'collected_at' => now(),
        ]);
        $run->update([
            'status' => ResearchRunStatus::Completed,
            'collected_result_count' => 1,
            'enriched_result_count' => 1,
            'progress_percent' => 100,
            'completed_at' => now(),
        ]);

        return $run->fresh();
    }

    private function candidate(DiscoveryRun $run): NicheCandidate
    {
        return $run->candidates()->create([
            'phrase' => 'compact storage systems',
            'cluster_key' => 'cluster-one',
            'summary' => 'Observed evidence.',
            'evidence' => ['observed_signal' => 'returned_video_breakout'],
            'overall_score' => 70,
            'confidence_score' => 60,
            'formula_version' => 'discovery-breakout-v1',
            'status' => NicheCandidateStatus::New,
        ]);
    }
}
