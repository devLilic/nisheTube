<?php

namespace Tests\Feature\Research;

use App\Domain\Research\Actions\CreateResearchQuery;
use App\Domain\Research\Actions\CreateResearchRun;
use App\Domain\Research\Enums\ResearchRunStatus;
use App\Jobs\Discovery\GenerateDiscoveryCandidates;
use App\Jobs\Research\CollectResearchRunSearch;
use App\Models\Channel;
use App\Models\DiscoveryRun;
use App\Models\Market;
use App\Models\ResearchRun;
use App\Models\User;
use App\Models\Video;
use Database\Seeders\MarketSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ResearchIntakeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MarketSeeder::class);
        Queue::fake();
    }

    public function test_validation_intake_exposes_configuration_backed_presets_and_exact_preflight(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('research.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('research/create')
                ->has('submission_token')
                ->has('validation_presets', 9)
                ->where('validation_presets.0.label', 'Fast scan')
                ->where('validation_presets.8.label', 'Shorts opportunity')
                ->where('preflight.search_request_cost', (int) config('youtube.endpoints')['search.list']['cost'])
                ->where('preflight.search_request_measure', (string) config('youtube.quota_buckets.search.measure'))
                ->where('preflight.max_results_per_request', 50));
    }

    public function test_validation_submission_freezes_intake_values_and_reuses_one_owner_submission(): void
    {
        $user = User::factory()->create();
        $token = (string) Str::uuid();
        $payload = [
            'submission_token' => $token,
            'workflow_mode' => 'validate_idea',
            'preset_key' => 'small_channel_opportunity',
            'query_text' => 'small workshop documentary',
            'market_key' => 'global_en',
            'language' => 'en',
            'requested_result_count' => 50,
            'published_window' => 'past_three_months',
            'search_order' => 'relevance',
            'video_duration' => 'long',
            'video_category_id' => '26',
            'content_format' => 'long_form',
            'target_channel_size' => 'small',
        ];

        $first = $this->actingAs($user)->post(route('research.store'), $payload);
        $run = ResearchRun::query()->sole();
        $first->assertRedirect(route('research.runs.show', $run));

        $this->assertSame($token, $run->submission_token);
        $this->assertSame('validate_idea', $run->parameters['workflow_mode']);
        $this->assertSame('small_channel_opportunity', $run->parameters['preset_key']);
        $this->assertSame('long_form', $run->parameters['content_format']);
        $this->assertSame('small', $run->parameters['target_channel_size']);

        $this->actingAs($user)->post(route('research.store'), array_replace($payload, [
            'query_text' => 'changed duplicate payload',
        ]))->assertRedirect(route('research.runs.show', $run));

        $this->assertDatabaseCount('research_runs', 1);
        $this->assertDatabaseCount('research_queries', 1);
        Queue::assertPushed(CollectResearchRunSearch::class, 1);
    }

    public function test_intake_rejects_language_market_mismatch_and_invalid_lenses_without_work(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('research.store'), [
                'submission_token' => (string) Str::uuid(),
                'workflow_mode' => 'validate_idea',
                'preset_key' => 'unknown',
                'query_text' => 'idee validare',
                'market_key' => 'ro_ro',
                'language' => 'ru',
                'requested_result_count' => 50,
                'published_window' => 'past_month',
                'search_order' => 'relevance',
                'video_duration' => 'any',
                'content_format' => 'podcast',
                'target_channel_size' => 'micro',
            ])
            ->assertSessionHasErrors(['preset_key', 'language', 'content_format', 'target_channel_size']);

        $this->assertDatabaseCount('research_runs', 0);
        Queue::assertNothingPushed();
    }

    public function test_discovery_intake_freezes_and_idempotently_reuses_market_lenses(): void
    {
        $user = User::factory()->create();
        $market = Market::query()->where('key', 'global_en')->firstOrFail();
        $sample = $this->completedSample($user, $market);
        $token = (string) Str::uuid();
        $payload = [
            'submission_token' => $token,
            'market_key' => 'global_en',
            'language' => 'en',
            'content_format' => 'shorts',
            'period' => 'past_month',
            'target_channel_size' => 'small',
            'sample_per_seed' => 25,
            'candidate_limit' => 20,
            'seeds' => [[
                'query' => 'compact living',
                'research_run_id' => $sample->public_id,
            ]],
        ];

        $this->actingAs($user)->post(route('discovery.store'), $payload)->assertRedirect();
        $run = DiscoveryRun::query()->sole();
        $this->assertSame('shorts', $run->parameters['content_format']);
        $this->assertSame('past_month', $run->parameters['period']);
        $this->assertNotNull($run->parameters['period_after']);
        $this->assertSame('small', $run->parameters['target_channel_size']);

        $this->actingAs($user)->post(route('discovery.store'), $payload)
            ->assertRedirect(route('discovery.runs.show', $run));

        $this->assertDatabaseCount('discovery_runs', 1);
        Queue::assertPushed(GenerateDiscoveryCandidates::class, 1);
    }

    private function completedSample(User $user, Market $market): ResearchRun
    {
        $query = app(CreateResearchQuery::class)->handle($user, $market, 'compact living');
        $run = app(CreateResearchRun::class)->handle($user, $query, 25);
        $channel = Channel::query()->create([
            'provider' => 'youtube',
            'provider_channel_id' => 'channel-intake',
            'title' => 'Intake channel',
        ]);
        $video = Video::query()->create([
            'provider' => 'youtube',
            'provider_video_id' => 'video-intake',
            'channel_id' => $channel->id,
            'title' => 'Compact living',
            'published_at' => now()->subDay(),
        ]);
        $snapshot = $run->videoSnapshots()->create([
            'video_id' => $video->id,
            'view_count' => 1000,
            'views_per_day' => 100,
            'duration_seconds' => 120,
            'collected_at' => now(),
        ]);
        $run->videos()->attach($video->id, [
            'video_snapshot_id' => $snapshot->id,
            'result_rank' => 1,
            'page_number' => 1,
            'provider_order' => 1,
        ]);
        $run->update([
            'status' => ResearchRunStatus::Completed,
            'progress_percent' => 100,
            'completed_at' => now(),
        ]);

        return $run->fresh();
    }
}
