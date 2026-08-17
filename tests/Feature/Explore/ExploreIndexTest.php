<?php

namespace Tests\Feature\Explore;

use App\Domain\Analyzer\Actions\CreateAnalyzerRun;
use App\Domain\Analyzer\Enums\AnalyzerRunStatus;
use App\Domain\Collection\Enums\CollectionCachePolicy;
use App\Domain\Discovery\Enums\DiscoveryRunStatus;
use App\Domain\Discovery\Enums\NicheCandidateStatus;
use App\Domain\Research\Actions\CreateResearchQuery;
use App\Domain\Research\Actions\CreateResearchRun;
use App\Domain\Research\Enums\ResearchRunStatus;
use App\Models\ApiUsageEvent;
use App\Models\Channel;
use App\Models\ChannelSnapshot;
use App\Models\DiscoveryRun;
use App\Models\ExplorePreset;
use App\Models\Favorite;
use App\Models\Market;
use App\Models\NicheCandidate;
use App\Models\ResearchRun;
use App\Models\User;
use App\Models\UserEntityObservation;
use App\Models\Video;
use App\Models\VideoSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class ExploreIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_explore_is_owner_scoped_paginated_and_performs_no_provider_io(): void
    {
        Http::preventStrayRequests();
        $market = $this->market();
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $this->researchEvidence($owner, $market, 25, 'Owner evidence');
        $this->researchEvidence($other, $market, 1, 'Foreign secret');

        $beforeUsage = ApiUsageEvent::query()->count();
        $this->actingAs($owner)->get(route('explore.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('explore/index')
                ->where('capabilities.provider_io_during_browsing', false)
                ->where('counts.video', 25)
                ->has('results.data', 24)
                ->where('results.current_page', 1)
                ->where('results.last_page', 2)
                ->where('results.total', 25)
                ->where('results.data.0.entity_type', 'video')
                ->where('results.data.0.sources.0', 'research')
                ->missing('results.data.24'));

        $this->actingAs($owner)->get(route('explore.index', ['page' => 2]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->has('results.data', 1)
                ->where('results.total', 25));

        $this->actingAs($owner)->get(route('explore.index', [
            'entity_type' => 'channel',
            'source' => 'research',
        ]))->assertOk()->assertInertia(fn (Assert $page): Assert => $page
            ->has('results.data', 1)
            ->where('results.data.0.entity_type', 'channel')
            ->where('results.data.0.sources.0', 'research'));

        $this->assertSame($beforeUsage, ApiUsageEvent::query()->count());
    }

    public function test_contextual_filters_do_not_leak_foreign_evidence_or_coerce_missing_metrics(): void
    {
        $market = $this->market();
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $owned = $this->researchEvidence($owner, $market, 2, 'Romanian studio');
        $foreign = $this->researchEvidence($other, $market, 1, 'Romanian secret');
        $favoriteVideo = $owned->videos()->firstOrFail();
        Favorite::query()->create([
            'user_id' => $owner->id,
            'target_type' => 'video',
            'target_id' => $favoriteVideo->id,
        ]);
        Favorite::query()->create([
            'user_id' => $other->id,
            'target_type' => 'video',
            'target_id' => $foreign->videos()->firstOrFail()->id,
        ]);

        $this->actingAs($owner)->get(route('explore.index', [
            'source' => 'library',
            'organization' => 'favorite',
            'market' => 'ro_ro',
            'category' => '26',
            'topic' => 'studio',
        ]))->assertOk()->assertInertia(fn (Assert $page): Assert => $page
            ->has('results.data', 1)
            ->where('results.data.0.id', $favoriteVideo->provider_video_id)
            ->where('results.data.0.favorite', true)
            ->where('results.data.0.performance', null)
            ->where('results.data.0.score', null)
            ->where('results.data.0.partial', true));

        $this->actingAs($owner)->get(route('explore.index', ['channel_size' => 'large']))
            ->assertOk()->assertInertia(fn (Assert $page): Assert => $page->has('results.data', 0));
    }

    public function test_candidates_have_discovery_and_library_context_with_explicit_validation_action(): void
    {
        $market = $this->market();
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $owned = $this->candidate($owner, $market, 'Home studio lighting');
        $this->candidate($other, $market, 'Foreign candidate');
        Favorite::query()->create([
            'user_id' => $owner->id,
            'target_type' => 'niche_candidate',
            'target_id' => $owned->id,
        ]);

        $this->actingAs($owner)->get(route('explore.index', [
            'entity_type' => 'candidate',
            'source' => 'library',
            'market' => 'ro_ro',
            'topic' => 'lighting',
            'min_score' => 70,
            'min_confidence' => 60,
        ]))->assertOk()->assertInertia(fn (Assert $page): Assert => $page
            ->where('counts.candidate', 1)
            ->has('results.data', 1)
            ->where('results.data.0.id', $owned->public_id)
            ->where('results.data.0.sources', ['discovery', 'library'])
            ->where('results.data.0.validate_url', "/discover/candidates/{$owned->public_id}/validate")
            ->where('capabilities.watchlist_available', true)
            ->where('capabilities.workspace_available', true));
    }

    public function test_analyzer_performance_breakout_and_channel_size_filters_use_stored_metrics(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $owned = $this->analyzerEvidence($owner, 'owned-analyzer-video', 150000, 4200, 'breakout');
        $this->analyzerEvidence($other, 'foreign-analyzer-video', 150000, 9000, 'breakout');

        $this->actingAs($owner)->get(route('explore.index', [
            'source' => 'analyzer',
            'breakout' => 'breakout',
            'channel_size' => 'large',
            'min_performance' => 4000,
            'sort' => 'performance_desc',
        ]))->assertOk()->assertInertia(fn (Assert $page): Assert => $page
            ->has('results.data', 1)
            ->where('results.data.0.id', $owned->provider_video_id)
            ->where('results.data.0.performance', 4200)
            ->where('results.data.0.breakout_class', 'breakout')
            ->where('results.data.0.subscriber_count', 150000)
            ->where('results.data.0.sources', ['analyzer']));

        $this->actingAs($owner)->get(route('explore.index', [
            'entity_type' => 'channel',
            'source' => 'analyzer',
            'breakout' => 'breakout',
            'channel_size' => 'large',
            'min_performance' => 8000,
        ]))->assertOk()->assertInertia(fn (Assert $page): Assert => $page
            ->has('results.data', 1)
            ->where('results.data.0.performance', 9000)
            ->where('results.data.0.breakout_class', 'breakout')
            ->where('results.data.0.sources', ['analyzer']));
    }

    public function test_invalid_or_unbounded_filters_are_rejected_and_guests_are_redirected(): void
    {
        $owner = User::factory()->create();

        $this->get(route('explore.index'))->assertRedirect(route('login'));
        $this->actingAs($owner)->get(route('explore.index', [
            'entity_type' => 'all',
            'min_score' => 101,
            'page' => 10001,
        ]))->assertSessionHasErrors(['entity_type', 'min_score', 'page']);
    }

    public function test_saved_presets_are_validated_durable_and_owner_scoped(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($owner)->post(route('explore.presets.store'), [
            'name' => 'Romanian candidates',
            'filters' => [
                'entity_type' => 'candidate',
                'source' => 'discovery',
                'market' => 'ro_ro',
                'min_score' => 70,
                'sort' => 'score_desc',
            ],
        ])->assertRedirect();

        $preset = ExplorePreset::query()->where('user_id', $owner->id)->firstOrFail();
        $this->assertSame('Romanian candidates', $preset->name);
        $this->assertSame('candidate', $preset->filters['entity_type']);
        $this->assertEquals(70.0, $preset->filters['min_score']);

        $this->actingAs($owner)->get(route('explore.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->has('presets', 1)
                ->where('presets.0.public_id', $preset->public_id)
                ->where('presets.0.filters.sort', 'score_desc'));
        $this->actingAs($other)->get(route('explore.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page->has('presets', 0));
        $this->actingAs($other)->delete(route('explore.presets.destroy', $preset))->assertForbidden();

        $this->actingAs($owner)->post(route('explore.presets.store'), [
            'name' => 'Invalid',
            'filters' => ['min_score' => 101],
        ])->assertSessionHasErrors('filters.min_score');
    }

    public function test_analyzer_links_preserve_the_active_explore_page_and_filters(): void
    {
        $owner = User::factory()->create();
        $video = $this->analyzerEvidence($owner, 'return-state-video', 150000, 4200, 'breakout');

        $this->actingAs($owner)->get(route('explore.index', [
            'source' => 'analyzer',
            'breakout' => 'breakout',
            'page' => 1,
        ]))->assertOk()->assertInertia(fn (Assert $page): Assert => $page
            ->where('results.data.0.id', $video->provider_video_id)
            ->where('results.data.0.analyzer_url', fn (string $url): bool => str_contains(
                rawurldecode($url),
                '/explore?entity_type=video&source=analyzer&breakout=breakout&organization=all&sort=latest&page=1',
            )));
    }

    private function market(): Market
    {
        return Market::query()->create([
            'key' => 'ro_ro',
            'name' => 'Romania / Romanian',
            'region_code' => 'RO',
            'relevance_language' => 'ro',
            'is_enabled' => true,
            'sort_order' => 1,
        ]);
    }

    private function researchEvidence(User $user, Market $market, int $count, string $title): ResearchRun
    {
        $researchQuery = app(CreateResearchQuery::class)->handle($user, $market, $title);
        $run = app(CreateResearchRun::class)->handle($user, $researchQuery, $count);
        $channel = Channel::query()->create([
            'provider' => 'youtube',
            'provider_channel_id' => 'channel-'.$run->id,
            'title' => $title.' channel',
        ]);

        foreach (range(1, $count) as $position) {
            $video = Video::query()->create([
                'provider' => 'youtube',
                'provider_video_id' => sprintf('video-%d-%02d', $run->id, $position),
                'channel_id' => $channel->id,
                'title' => "{$title} {$position}",
                'published_at' => now()->subDays($position),
                'category_id' => '26',
            ]);
            $run->videos()->attach($video->id, [
                'result_rank' => $position,
                'page_number' => 1,
                'provider_order' => $position,
            ]);
        }

        $run->update([
            'status' => ResearchRunStatus::Completed,
            'collected_result_count' => $count,
            'enriched_result_count' => $count,
            'progress_percent' => 100,
            'completed_at' => now(),
        ]);

        return $run->fresh();
    }

    private function candidate(User $user, Market $market, string $phrase): NicheCandidate
    {
        $run = DiscoveryRun::query()->create([
            'user_id' => $user->id,
            'market_id' => $market->id,
            'status' => DiscoveryRunStatus::Completed,
            'market_key' => $market->key,
            'region_code' => $market->region_code,
            'relevance_language' => $market->relevance_language,
            'parameters' => [],
            'seed_count' => 1,
            'candidate_count' => 1,
            'progress_percent' => 100,
            'completed_at' => now(),
        ]);

        return $run->candidates()->create([
            'phrase' => $phrase,
            'cluster_key' => sha1($phrase),
            'summary' => 'Observed stored evidence.',
            'evidence' => ['source' => 'stored'],
            'overall_score' => 75,
            'confidence_score' => 65,
            'formula_version' => 'discovery-breakout-v1',
            'status' => NicheCandidateStatus::New,
        ]);
    }

    private function analyzerEvidence(
        User $user,
        string $providerVideoId,
        int $subscribers,
        int $viewsPerDay,
        string $breakoutClass,
    ): Video {
        $channel = Channel::query()->create([
            'provider' => 'youtube',
            'provider_channel_id' => 'channel-'.$providerVideoId,
            'title' => 'Analyzer channel',
        ]);
        $video = Video::query()->create([
            'provider' => 'youtube',
            'provider_video_id' => $providerVideoId,
            'channel_id' => $channel->id,
            'title' => 'Stored Analyzer evidence',
            'published_at' => now()->subDays(10),
        ]);
        $run = app(CreateAnalyzerRun::class)->handle(
            $user,
            $providerVideoId,
            CollectionCachePolicy::AllowFreshCache,
            'manual',
        );
        $run->update(['video_id' => $video->id, 'channel_id' => $channel->id]);
        $channelSnapshot = ChannelSnapshot::query()->create([
            'channel_id' => $channel->id,
            'collection_run_id' => $run->collection_run_id,
            'subscriber_count' => $subscribers,
            'view_count' => 500000,
            'video_count' => 50,
            'subscriber_count_hidden' => false,
            'collected_at' => now(),
        ]);
        $videoSnapshot = VideoSnapshot::query()->create([
            'video_id' => $video->id,
            'collection_run_id' => $run->collection_run_id,
            'view_count' => 42000,
            'collected_at' => now(),
        ]);
        UserEntityObservation::query()->create([
            'user_id' => $user->id,
            'subject_type' => 'video',
            'subject_id' => $video->id,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'last_fetched_at' => now(),
            'first_video_snapshot_id' => $videoSnapshot->id,
            'latest_video_snapshot_id' => $videoSnapshot->id,
            'first_observed_count' => 42000,
        ]);
        UserEntityObservation::query()->create([
            'user_id' => $user->id,
            'subject_type' => 'channel',
            'subject_id' => $channel->id,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'last_fetched_at' => now(),
            'first_channel_snapshot_id' => $channelSnapshot->id,
            'latest_channel_snapshot_id' => $channelSnapshot->id,
            'first_observed_count' => $subscribers,
        ]);
        $run->videoMetrics()->create([
            'video_id' => $video->id,
            'age_seconds' => 864000,
            'lifetime_views_per_day' => $viewsPerDay,
            'breakout_class' => $breakoutClass,
            'recent_comparison_count' => 10,
            'calculation_version' => 'video-profile-v1',
            'input_summary' => [],
            'calculated_at' => now(),
        ]);
        $run->channelMetrics()->create([
            'channel_id' => $channel->id,
            'recent_valid_count' => 10,
            'recent_requested_count' => 10,
            'coverage_percent' => 100,
            'median_views' => 9000,
            'duration_distribution' => [],
            'category_distribution' => [],
            'strong_count' => 2,
            'breakout_count' => 1,
            'calculation_version' => 'channel-baseline-v1',
            'input_summary' => [],
            'calculated_at' => now(),
        ]);
        $run->update([
            'status' => AnalyzerRunStatus::Completed,
            'progress_percent' => 100,
            'calculated_at' => now(),
            'completed_at' => now(),
        ]);

        return $video;
    }
}
