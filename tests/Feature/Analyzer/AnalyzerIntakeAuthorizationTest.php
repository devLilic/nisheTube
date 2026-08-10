<?php

namespace Tests\Feature\Analyzer;

use App\Domain\Analyzer\Actions\CreateAnalyzerRun;
use App\Domain\Analyzer\Actions\TransitionAnalyzerRun;
use App\Domain\Analyzer\Enums\AnalyzerRunStatus;
use App\Domain\Collection\Enums\CollectionCachePolicy;
use App\Domain\Research\Actions\CreateResearchQuery;
use App\Domain\Research\Actions\CreateResearchRun;
use App\Jobs\Analyzer\CollectAnalyzerRun;
use App\Models\AnalyzerRun;
use App\Models\Channel;
use App\Models\Market;
use App\Models\User;
use App\Models\Video;
use Database\Seeders\MarketSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class AnalyzerIntakeAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MarketSeeder::class);
    }

    public function test_authenticated_user_can_queue_a_canonical_owner_scoped_analysis(): void
    {
        Queue::fake();
        $owner = User::factory()->create();

        $response = $this->actingAs($owner)->post(route('analyzer.store'), [
            'video_reference' => 'https://www.youtube.com/shorts/dQw4w9WgXcQ',
        ]);

        $run = AnalyzerRun::query()->sole();
        $response->assertRedirect(route('analyzer.runs.show', $run));
        $this->assertSame($owner->id, $run->user_id);
        $this->assertSame('dQw4w9WgXcQ', $run->target_provider_id);
        $this->assertSame(AnalyzerRunStatus::Queued, $run->status);
        $this->assertSame('allow_fresh_cache', $run->cache_policy->value);
        $this->assertSame($owner->id, $run->collectionRun->user_id);
        Queue::assertPushed(CollectAnalyzerRun::class, fn (CollectAnalyzerRun $job): bool => $job->analyzerRunId === $run->id);
    }

    public function test_invalid_or_ambiguous_input_never_creates_or_queues_a_run(): void
    {
        Queue::fake();

        $this->actingAs(User::factory()->create())
            ->from(route('analyzer.index'))
            ->post(route('analyzer.store'), [
                'video_reference' => 'https://youtube.com.evil.test/watch?v=dQw4w9WgXcQ',
            ])
            ->assertRedirect(route('analyzer.index'))
            ->assertSessionHasErrors('video_reference');

        $this->assertDatabaseCount('analyzer_runs', 0);
        $this->assertDatabaseCount('collection_runs', 0);
        Queue::assertNothingPushed();
    }

    public function test_guest_and_other_users_cannot_read_or_refresh_analyzer_runs(): void
    {
        Queue::fake();
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($owner)->post(route('analyzer.store'), ['video_reference' => 'dQw4w9WgXcQ']);
        $run = AnalyzerRun::query()->sole();

        auth()->logout();
        $this->get(route('analyzer.runs.show', $run))->assertRedirect(route('login'));
        $this->actingAs($other)->get(route('analyzer.runs.show', $run))->assertForbidden();
        $this->actingAs($other)->post(route('analyzer.runs.refresh', $run), ['mode' => 'force_refresh'])->assertForbidden();
        $this->assertDatabaseCount('analyzer_runs', 1);
    }

    public function test_landing_and_empty_profile_states_are_owner_scoped(): void
    {
        Queue::fake();
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($owner)->post(route('analyzer.store'), ['video_reference' => 'dQw4w9WgXcQ']);
        $run = AnalyzerRun::query()->sole();

        $this->actingAs($owner)->get(route('analyzer.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('analyzer/index')
                ->has('recent_video_runs.data', 1)
                ->has('recent_channel_runs.data', 0)
                ->where('recent_video_runs.data.0.public_id', $run->public_id)
                ->where('recent_video_runs.data.0.display_label', 'Video title pending')
                ->where('recent_video_runs.data.0.display_identity.provider_id', 'dQw4w9WgXcQ')
                ->where('recent_video_runs.data.0.display_identity.thumbnail_url', null)
                ->where('recent_video_runs.data.0.display_identity.is_resolved', false)
                ->where('recent_video_runs.per_page', 10));
        $this->actingAs($other)->get(route('analyzer.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->has('recent_video_runs.data', 0)
                ->has('recent_channel_runs.data', 0));
        $this->actingAs($owner)->get(route('analyzer.runs.show', $run))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('analyzer/show')
                ->where('run.status', 'queued')
                ->where('run.video', null)
                ->where('run.channel', null));
    }

    public function test_stored_channel_video_handoff_prefills_video_analyzer_and_safe_return_path_without_provider_io(): void
    {
        Queue::fake();
        $owner = User::factory()->create();
        $source = app(CreateAnalyzerRun::class)->handleTarget(
            $owner,
            'channel',
            'UCchannelHandoff00000001',
            CollectionCachePolicy::AllowFreshCache,
        );

        $this->actingAs($owner)->get(route('analyzer.index', [
            'video' => 'dQw4w9WgXcQ',
            'return_to' => route('analyzer.runs.show', $source, false),
        ]))->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('prefill.target_kind', 'video')
                ->where('prefill.target_reference', 'dQw4w9WgXcQ')
                ->where('prefill.origin_kind', 'manual')
                ->where('prefill.return_to', "/analyzer/runs/{$source->public_id}"));

        $this->assertDatabaseCount('api_usage_events', 0);
        Queue::assertNothingPushed();
    }

    public function test_stored_catalog_titles_and_channel_names_label_legacy_analyzer_runs_without_provider_calls(): void
    {
        Queue::fake();
        $owner = User::factory()->create();
        $videoRun = app(CreateAnalyzerRun::class)->handle(
            $owner,
            'storedVid01',
            CollectionCachePolicy::AllowFreshCache,
        );
        $channel = Channel::query()->create([
            'provider' => 'youtube',
            'provider_channel_id' => 'storedChannel000000000001',
            'title' => 'Stored Author Channel',
        ]);
        Video::query()->create([
            'provider' => 'youtube',
            'provider_video_id' => 'storedVid01',
            'channel_id' => $channel->id,
            'title' => 'A useful stored video title that should replace the provider ID',
            'published_at' => now(),
        ]);
        $channelRun = app(CreateAnalyzerRun::class)->handleTarget(
            $owner,
            'channel',
            $channel->provider_channel_id,
            CollectionCachePolicy::AllowFreshCache,
        );

        $this->actingAs($owner)->get(route('analyzer.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->has('recent_video_runs.data', 1)
                ->has('recent_channel_runs.data', 1)
                ->where('recent_channel_runs.data.0.public_id', $channelRun->public_id)
                ->where('recent_channel_runs.data.0.display_label', 'Stored Author Channel')
                ->where('recent_video_runs.data.0.public_id', $videoRun->public_id)
                ->where('recent_video_runs.data.0.display_label', 'A useful stored video title that should replace the provider ID')
                ->where('recent_video_runs.data.0.display_identity.channel_title', 'Stored Author Channel')
                ->where('recent_video_runs.data.0.display_identity.provider_id', 'storedVid01'));

        $this->actingAs($owner)->get(route('analyzer.runs.show', $videoRun))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('run.display_label', 'A useful stored video title that should replace the provider ID')
                ->where('run.display_identity.video_title', 'A useful stored video title that should replace the provider ID')
                ->where('run.display_identity.channel_title', 'Stored Author Channel')
                ->where('run.video', null));

        $this->assertDatabaseCount('api_usage_events', 0);
    }

    public function test_video_and_channel_histories_are_owner_scoped_thumbnail_aware_and_independently_paginated_by_ten(): void
    {
        Queue::fake();
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $catalogChannel = Channel::query()->create([
            'provider' => 'youtube',
            'provider_channel_id' => 'historyCatalogChannel00001',
            'title' => 'Video history author',
        ]);
        $videoRuns = [];
        $channelRuns = [];

        foreach (range(1, 11) as $index) {
            $videoId = sprintf('historyVid%02d', $index);
            $channelId = sprintf('historyChannel%011d', $index);
            Video::query()->create([
                'provider' => 'youtube',
                'provider_video_id' => $videoId,
                'channel_id' => $catalogChannel->id,
                'title' => "History video {$index}",
                'thumbnail_url' => "https://i.ytimg.com/vi/{$videoId}/hqdefault.jpg",
                'published_at' => now()->subDays($index),
            ]);
            Channel::query()->create([
                'provider' => 'youtube',
                'provider_channel_id' => $channelId,
                'title' => "History channel {$index}",
                'thumbnail_url' => "https://yt3.ggpht.com/history-channel-{$index}",
            ]);
            $videoRuns[$index] = app(CreateAnalyzerRun::class)->handleTarget(
                $owner,
                'video',
                $videoId,
                CollectionCachePolicy::AllowFreshCache,
            );
            $channelRuns[$index] = app(CreateAnalyzerRun::class)->handleTarget(
                $owner,
                'channel',
                $channelId,
                CollectionCachePolicy::AllowFreshCache,
            );
        }

        app(CreateAnalyzerRun::class)->handleTarget($other, 'video', 'foreignVid01', CollectionCachePolicy::AllowFreshCache);
        app(CreateAnalyzerRun::class)->handleTarget($other, 'channel', 'foreignChannel00000000001', CollectionCachePolicy::AllowFreshCache);

        $this->actingAs($owner)->get(route('analyzer.index', [
            'video_page' => 2,
            'channel_page' => 1,
        ]))->assertInertia(fn (Assert $page): Assert => $page
            ->has('recent_video_runs.data', 1)
            ->has('recent_channel_runs.data', 10)
            ->where('recent_video_runs.current_page', 2)
            ->where('recent_video_runs.total', 11)
            ->where('recent_video_runs.per_page', 10)
            ->where('recent_video_runs.data.0.public_id', $videoRuns[1]->public_id)
            ->where('recent_video_runs.data.0.display_identity.thumbnail_url', 'https://i.ytimg.com/vi/historyVid01/hqdefault.jpg')
            ->where('recent_channel_runs.current_page', 1)
            ->where('recent_channel_runs.total', 11)
            ->where('recent_channel_runs.data.0.public_id', $channelRuns[11]->public_id)
            ->where('recent_channel_runs.data.0.display_identity.thumbnail_url', 'https://yt3.ggpht.com/history-channel-11'));

        $this->actingAs($owner)->get(route('analyzer.index', [
            'video_page' => 1,
            'channel_page' => 2,
        ]))->assertInertia(fn (Assert $page): Assert => $page
            ->has('recent_video_runs.data', 10)
            ->has('recent_channel_runs.data', 1)
            ->where('recent_video_runs.current_page', 1)
            ->where('recent_channel_runs.current_page', 2)
            ->where('recent_channel_runs.data.0.public_id', $channelRuns[1]->public_id));

        Queue::assertNothingPushed();
        $this->assertDatabaseCount('api_usage_events', 0);
    }

    public function test_search_origin_is_kept_only_for_an_owned_run_containing_the_canonical_video(): void
    {
        Queue::fake();
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $query = app(CreateResearchQuery::class)->handle(
            $owner,
            Market::query()->where('key', 'global_en')->firstOrFail(),
            'source-aware video',
        );
        $researchRun = app(CreateResearchRun::class)->handle($owner, $query, 25);
        $channel = Channel::query()->create([
            'provider' => 'youtube',
            'provider_channel_id' => 'channel000001',
            'title' => 'Source channel',
        ]);
        $video = Video::query()->create([
            'provider' => 'youtube',
            'provider_video_id' => 'dQw4w9WgXcQ',
            'channel_id' => $channel->id,
            'title' => 'Source video',
            'published_at' => now(),
        ]);
        $researchRun->videos()->attach($video->id, [
            'result_rank' => 1,
            'page_number' => 1,
            'provider_order' => 1,
        ]);

        $this->actingAs($owner)->post(route('analyzer.store'), [
            'video_reference' => $video->provider_video_id,
            'origin_kind' => 'search',
            'origin_reference' => $researchRun->public_id,
        ]);
        $ownedAnalyzer = AnalyzerRun::query()->latest('id')->firstOrFail();
        $this->assertSame('search', $ownedAnalyzer->origin_kind);
        $this->assertSame($researchRun->public_id, $ownedAnalyzer->origin_reference);

        $this->actingAs($other)->post(route('analyzer.store'), [
            'video_reference' => $video->provider_video_id,
            'origin_kind' => 'search',
            'origin_reference' => $researchRun->public_id,
        ]);
        $foreignAnalyzer = AnalyzerRun::query()->latest('id')->firstOrFail();
        $this->assertSame('manual', $foreignAnalyzer->origin_kind);
        $this->assertNull($foreignAnalyzer->origin_reference);
    }

    public function test_force_refresh_creates_a_new_immutable_attempt_linked_to_the_terminal_source(): void
    {
        Queue::fake();
        $owner = User::factory()->create();
        $source = app(CreateAnalyzerRun::class)->handle(
            $owner,
            'dQw4w9WgXcQ',
            CollectionCachePolicy::AllowFreshCache,
        );
        $source = app(TransitionAnalyzerRun::class)->handle($source, AnalyzerRunStatus::Failed, 'youtube_video_not_found', 'Unavailable.');

        $response = $this->actingAs($owner)->post(route('analyzer.runs.refresh', $source), [
            'mode' => 'force_refresh',
        ]);

        $refresh = AnalyzerRun::query()->whereKeyNot($source->id)->sole();
        $response->assertRedirect(route('analyzer.runs.show', $refresh));
        $this->assertSame(2, $refresh->attempt_number);
        $this->assertSame('force_refresh', $refresh->cache_policy->value);
        $this->assertSame('refresh', $refresh->origin_kind);
        $this->assertSame($source->public_id, $refresh->origin_reference);
        $this->assertSame(AnalyzerRunStatus::Failed, $source->fresh()->status);
        Queue::assertPushed(CollectAnalyzerRun::class, fn (CollectAnalyzerRun $job): bool => $job->analyzerRunId === $refresh->id);
    }
}
