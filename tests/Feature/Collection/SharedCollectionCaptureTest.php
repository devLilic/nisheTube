<?php

namespace Tests\Feature\Collection;

use App\Domain\Collection\Actions\CreateResearchCollectionRun;
use App\Domain\Collection\Actions\PersistCollectionObservationBatch;
use App\Domain\Collection\Actions\ResolveReusableObservationSources;
use App\Domain\Collection\Enums\CollectionCachePolicy;
use App\Domain\Collection\Enums\CollectionRunStatus;
use App\Domain\YouTube\Data\ChannelDetails;
use App\Domain\YouTube\Data\ChannelDetailsBatch;
use App\Domain\YouTube\Data\VideoDetails;
use App\Domain\YouTube\Data\VideoDetailsBatch;
use App\Models\CollectionRun;
use App\Models\User;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Tests\TestCase;

final class SharedCollectionCaptureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Date::setTestNow('2026-08-08 12:00:00 UTC');
    }

    protected function tearDown(): void
    {
        Date::setTestNow();

        parent::tearDown();
    }

    public function test_shared_persistence_captures_immutable_observations_without_a_legacy_research_run(): void
    {
        $collectionRun = $this->collectingRun(User::factory()->create());
        $batch = app(PersistCollectionObservationBatch::class)->handle(
            $collectionRun,
            $this->videoBatch(),
            $this->channelBatch(),
        );
        $observation = $batch->observation('shared-video');

        $this->assertNotNull($observation);
        $this->assertNull($observation->videoSnapshot->research_run_id);
        $this->assertNull($observation->channelSnapshot?->research_run_id);
        $this->assertSame($collectionRun->id, $observation->videoSnapshot->collection_run_id);
        $this->assertSame($collectionRun->id, $observation->channelSnapshot?->collection_run_id);
        $this->assertSame('1000.000000', $observation->videoSnapshot->views_per_day);
        $this->assertSame('10.00000000', $observation->videoSnapshot->views_to_subscribers_ratio);
    }

    public function test_cache_reuse_is_bounded_owner_scoped_and_force_refresh_bypasses_it(): void
    {
        config()->set('collection.freshness.minimum_seconds', 300);
        config()->set('collection.freshness.maximum_seconds', 3_600);
        $owner = User::factory()->create();
        $source = $this->collectingRun($owner);
        app(PersistCollectionObservationBatch::class)->handle(
            $source,
            $this->videoBatch(),
            $this->channelBatch(),
        );
        $source->update([
            'status' => CollectionRunStatus::Completed,
            'processed_count' => 1,
            'progress_percent' => 100,
            'completed_at' => '2026-08-08 12:00:00',
        ]);
        $allowed = app(CreateResearchCollectionRun::class)->handle(
            user: $owner,
            attemptNumber: 2,
            requestedCount: 1,
            frozenRequest: ['target' => 'shared-video'],
            cachePolicy: CollectionCachePolicy::AllowFreshCache,
            freshnessWindowSeconds: 99_999,
        );
        $forced = app(CreateResearchCollectionRun::class)->handle(
            user: $owner,
            attemptNumber: 3,
            requestedCount: 1,
            frozenRequest: ['target' => 'shared-video'],
            cachePolicy: CollectionCachePolicy::ForceRefresh,
            freshnessWindowSeconds: 1,
        );
        $foreign = app(CreateResearchCollectionRun::class)->handle(
            user: User::factory()->create(),
            attemptNumber: 1,
            requestedCount: 1,
            frozenRequest: ['target' => 'shared-video'],
            cachePolicy: CollectionCachePolicy::AllowFreshCache,
            freshnessWindowSeconds: 3_600,
        );

        $resolver = app(ResolveReusableObservationSources::class);

        $this->assertSame(3_600, $allowed->configuration_context['freshness_window_seconds']);
        $this->assertSame(300, $forced->configuration_context['freshness_window_seconds']);
        $this->assertSame(1, $resolver->handle($allowed, ['shared-video'])->count());
        $this->assertSame(0, $resolver->handle($forced, ['shared-video'])->count());
        $this->assertSame(0, $resolver->handle($foreign, ['shared-video'])->count());
    }

    private function collectingRun(User $user): CollectionRun
    {
        $run = app(CreateResearchCollectionRun::class)->handle(
            user: $user,
            attemptNumber: 1,
            requestedCount: 1,
            frozenRequest: ['target' => 'shared-video'],
        );
        $run->update([
            'status' => CollectionRunStatus::Collecting,
            'started_at' => '2026-08-08 12:00:00',
        ]);

        return $run->fresh();
    }

    private function videoBatch(): VideoDetailsBatch
    {
        return new VideoDetailsBatch([
            new VideoDetails(
                videoId: 'shared-video',
                channelId: 'shared-channel',
                channelTitle: 'Shared channel',
                title: 'Shared video',
                thumbnailUrl: null,
                publishedAt: new DateTimeImmutable('2026-08-07 12:00:00 UTC'),
                durationSeconds: 600,
                categoryId: '26',
                isShort: false,
                viewCount: 1000,
                likeCount: 50,
                commentCount: 5,
            ),
        ], new DateTimeImmutable('2026-08-08 12:00:00 UTC'));
    }

    private function channelBatch(): ChannelDetailsBatch
    {
        return new ChannelDetailsBatch([
            new ChannelDetails(
                channelId: 'shared-channel',
                title: 'Shared channel',
                customUrl: '@shared',
                thumbnailUrl: null,
                country: 'RO',
                subscriberCount: 100,
                viewCount: 10_000,
                videoCount: 10,
                subscriberCountHidden: false,
            ),
        ], new DateTimeImmutable('2026-08-08 12:00:00 UTC'));
    }
}
