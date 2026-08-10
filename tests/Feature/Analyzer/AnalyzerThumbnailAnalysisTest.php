<?php

namespace Tests\Feature\Analyzer;

use App\Domain\Analyzer\Actions\CreateAnalyzerRun;
use App\Domain\Analyzer\Enums\AnalyzerVideoRole;
use App\Domain\Collection\Enums\CollectionCachePolicy;
use App\Domain\Retention\Actions\CreateCleanupRun;
use App\Domain\Retention\Actions\ExecuteCleanup;
use App\Domain\Retention\Enums\CleanupMode;
use App\Domain\Retention\Services\BuildRetentionPlan;
use App\Domain\Thumbnails\Actions\CreateThumbnailAnalysis;
use App\Domain\Thumbnails\Contracts\ThumbnailAnalysisProvider;
use App\Domain\Thumbnails\Contracts\ThumbnailImageFetcher;
use App\Domain\Thumbnails\Data\ThumbnailFeatures;
use App\Domain\Thumbnails\Data\ThumbnailImagePayload;
use App\Domain\Thumbnails\Enums\ThumbnailAnalysisStatus;
use App\Http\ViewModels\AnalyzerRunViewModel;
use App\Jobs\Analyzer\AnalyzeThumbnails;
use App\Jobs\Retention\ExecuteCleanupRun;
use App\Models\AnalyzerRun;
use App\Models\AnalyzerRunVideo;
use App\Models\Channel;
use App\Models\ChannelSnapshot;
use App\Models\ThumbnailAnalysisProfile;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class AnalyzerThumbnailAnalysisTest extends TestCase
{
    use RefreshDatabase;

    public function test_owned_explicit_action_queues_and_persists_inferred_features_and_observed_associations(): void
    {
        Queue::fake();
        $owner = User::factory()->create();
        $run = $this->completedRun($owner, ['one.jpg', 'two.jpg', null]);
        $fetcher = new CountingThumbnailFetcher;
        $provider = new FixedThumbnailProvider;
        $this->app->instance(ThumbnailImageFetcher::class, $fetcher);
        $this->app->instance(ThumbnailAnalysisProvider::class, $provider);

        $this->get(route('analyzer.runs.thumbnails.store', $run))->assertMethodNotAllowed();
        $this->post(route('analyzer.runs.thumbnails.store', $run))->assertRedirect(route('login'));
        $this->actingAs($owner)->post(route('analyzer.runs.thumbnails.store', $run))
            ->assertRedirect(route('analyzer.runs.show', $run));
        Queue::assertPushed(AnalyzeThumbnails::class, 1);

        $profile = ThumbnailAnalysisProfile::query()->sole();
        (new AnalyzeThumbnails($profile->id))->handle($fetcher, $provider);
        $profile->refresh();

        $this->assertSame(ThumbnailAnalysisStatus::Partial, $profile->status);
        $this->assertSame('inferred', $profile->provenance);
        $this->assertSame(3, $profile->processed_image_count);
        $this->assertSame(2, $profile->available_image_count);
        $this->assertSame(1, $profile->unavailable_image_count);
        $this->assertSame(2, $fetcher->calls);
        $this->assertSame(2, $profile->items()->where('status', 'available')->count());
        $this->assertSame(1, $profile->items()->where('error_code', 'thumbnail_url_missing')->count());
        $aggregate = $profile->aggregates()->sole();
        $this->assertTrue($aggregate->meets_minimum_sample);
        $this->assertSame('150.0000', $aggregate->median_views);
        $this->assertSame('15.000000', $aggregate->median_views_per_day);
        $this->assertSame('50.0000', $aggregate->breakout_rate_percent);

        $payload = app(AnalyzerRunViewModel::class)->toArray($run->fresh());
        $this->assertSame('partial', $payload['thumbnail_analysis']['status']);
        $this->assertSame('thumbnail-visual-features-v1', $payload['thumbnail_analysis']['profile']['algorithm_version']);
        $this->assertArrayNotHasKey('bytes', $payload['thumbnail_analysis']['profile']['items'][0]);
        $this->actingAs($owner)->get(route('analyzer.runs.show', $run))->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('run.thumbnail_analysis.status', 'partial')
                ->where('run.thumbnail_analysis.profile.provenance', 'inferred'));

        $other = User::factory()->create();
        $this->actingAs($other)->post(route('analyzer.runs.thumbnails.store', $run))->assertForbidden();
    }

    public function test_retry_is_immutable_and_exact_cache_reuse_is_owner_scoped(): void
    {
        Queue::fake();
        $owner = User::factory()->create();
        $run = $this->completedRun($owner, ['shared.jpg', 'shared-two.jpg']);
        $fetcher = new CountingThumbnailFetcher;
        $provider = new FixedThumbnailProvider;
        $this->app->instance(ThumbnailAnalysisProvider::class, $provider);

        $first = app(CreateThumbnailAnalysis::class)->handle($owner, $run);
        (new AnalyzeThumbnails($first->id))->handle($fetcher, $provider);
        $this->assertSame(2, $fetcher->calls);

        $first->refresh();
        $this->expectExceptionMessage('Completed thumbnail analysis profiles are immutable.');
        try {
            $first->update(['minimum_sample_size' => 3]);
        } finally {
            $first->setRawAttributes($first->getOriginal(), true);
        }
    }

    public function test_failed_attempt_can_create_a_new_attempt_that_reuses_only_the_same_owners_exact_results(): void
    {
        Queue::fake();
        $owner = User::factory()->create();
        $run = $this->completedRun($owner, ['same.jpg', 'same-two.jpg']);
        $fetcher = new CountingThumbnailFetcher;
        $provider = new FixedThumbnailProvider;
        $this->app->instance(ThumbnailAnalysisProvider::class, $provider);

        $first = app(CreateThumbnailAnalysis::class)->handle($owner, $run);
        (new AnalyzeThumbnails($first->id))->handle($fetcher, $provider);
        ThumbnailAnalysisProfile::withoutEvents(fn () => $first->refresh()->update([
            'status' => ThumbnailAnalysisStatus::Failed,
            'failed_at' => now(),
        ]));
        $second = app(CreateThumbnailAnalysis::class)->handle($owner, $run);
        (new AnalyzeThumbnails($second->id))->handle($fetcher, $provider);

        $this->assertSame(2, $second->attempt_number);
        $this->assertSame(2, $second->refresh()->reused_image_count);
        $this->assertSame(2, $fetcher->calls);
        $this->assertDatabaseCount('thumbnail_analysis_profiles', 2);

        $other = User::factory()->create();
        $otherRun = $this->completedRun($other, ['same.jpg']);
        $otherProfile = app(CreateThumbnailAnalysis::class)->handle($other, $otherRun);
        (new AnalyzeThumbnails($otherProfile->id))->handle($fetcher, $provider);
        $this->assertSame(3, $fetcher->calls);
        $this->assertSame(0, $otherProfile->refresh()->reused_image_count);
    }

    public function test_thumbnail_records_are_counted_and_cascade_with_analyzer_retention(): void
    {
        Date::setTestNow('2026-01-01 12:00:00 UTC');
        $owner = User::factory()->create();
        $run = $this->completedRun($owner, ['retention-one.jpg', 'retention-two.jpg']);
        $fetcher = new CountingThumbnailFetcher;
        $provider = new FixedThumbnailProvider;
        $this->app->instance(ThumbnailAnalysisProvider::class, $provider);
        Queue::fake();
        $profile = app(CreateThumbnailAnalysis::class)->handle($owner, $run);
        (new AnalyzeThumbnails($profile->id))->handle($fetcher, $provider);

        Date::setTestNow('2026-08-10 12:00:00 UTC');
        $plan = app(BuildRetentionPlan::class)->handle($owner);
        $this->assertSame(1, $plan->counts['analyzer_runs']);
        $this->assertSame(1, $plan->counts['thumbnail_analysis_profiles']);
        $this->assertSame(2, $plan->counts['thumbnail_analysis_items']);
        $this->assertSame(1, $plan->counts['thumbnail_performance_aggregates']);

        $cleanup = app(CreateCleanupRun::class)->handle($owner, CleanupMode::ManualRetention, false, initiator: $owner);
        (new ExecuteCleanupRun($cleanup->id))->handle(app(ExecuteCleanup::class));
        $this->assertDatabaseMissing('thumbnail_analysis_profiles', ['id' => $profile->id]);
        $this->assertSame(1, $cleanup->fresh()->deleted_counts['thumbnail_analysis_profiles']);
        $this->assertSame(2, $cleanup->fresh()->deleted_counts['thumbnail_analysis_items']);
        $this->assertSame(1, $cleanup->fresh()->deleted_counts['thumbnail_performance_aggregates']);
        Date::setTestNow();
    }

    /** @param list<string|null> $thumbnailNames */
    private function completedRun(User $owner, array $thumbnailNames): AnalyzerRun
    {
        $run = app(CreateAnalyzerRun::class)->handleTarget($owner, 'channel', 'thumbnail-channel-'.uniqid(), CollectionCachePolicy::AllowFreshCache);
        $channel = Channel::query()->create([
            'provider' => 'youtube',
            'provider_channel_id' => 'channel-'.uniqid(),
            'title' => 'Thumbnail Lab',
        ]);
        $channelSnapshot = ChannelSnapshot::query()->create([
            'channel_id' => $channel->id,
            'collection_run_id' => $run->collection_run_id,
            'subscriber_count_hidden' => false,
            'collected_at' => now(),
        ]);
        $run->update(['channel_id' => $channel->id, 'channel_snapshot_id' => $channelSnapshot->id]);

        foreach ($thumbnailNames as $index => $name) {
            $video = Video::query()->create([
                'provider' => 'youtube',
                'provider_video_id' => 'thumbnail-video-'.uniqid()."-{$index}",
                'channel_id' => $channel->id,
                'title' => "Thumbnail fixture {$index}",
                'thumbnail_url' => $name === null ? null : "https://i.ytimg.com/vi/{$name}",
                'published_at' => now()->subDays($index + 1),
            ]);
            $snapshot = VideoSnapshot::query()->create([
                'video_id' => $video->id,
                'collection_run_id' => $run->collection_run_id,
                'view_count' => ($index + 1) * 100,
                'views_per_day' => ($index + 1) * 10,
                'collected_at' => now(),
            ]);
            AnalyzerRunVideo::query()->create([
                'analyzer_run_id' => $run->id,
                'video_id' => $video->id,
                'video_snapshot_id' => $snapshot->id,
                'channel_snapshot_id' => $channelSnapshot->id,
                'role' => AnalyzerVideoRole::ChannelRecentUpload,
                'source_position' => $index + 1,
                'breakout_class' => $index === 0 ? 'breakout' : 'normal',
                'threshold_version' => 'video-relative-performance-v1',
            ]);
        }
        $run->update(['status' => 'completed', 'progress_percent' => 100, 'completed_at' => now()]);

        return $run->fresh();
    }
}

final class CountingThumbnailFetcher implements ThumbnailImageFetcher
{
    public int $calls = 0;

    public function fetch(string $url): ThumbnailImagePayload
    {
        $this->calls++;

        return new ThumbnailImagePayload('transient-image-bytes', 'image/jpeg', hash('sha256', 'transient-image-bytes'));
    }
}

final class FixedThumbnailProvider implements ThumbnailAnalysisProvider
{
    public function name(): string
    {
        return 'test_thumbnail_features';
    }

    public function version(): string
    {
        return 'thumbnail-visual-features-v1';
    }

    public function analyze(ThumbnailImagePayload $image): ThumbnailFeatures
    {
        return new ThumbnailFeatures(480, 270, 1.7778, 60, 70, 50, 20, 'red', 'balanced', 'vivid', 'high contrast', 'balanced detail', 'red|balanced|balanced detail', 90);
    }
}
