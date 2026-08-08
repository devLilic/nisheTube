<?php

namespace Tests\Feature\Catalog;

use App\Domain\Research\Actions\CreateResearchQuery;
use App\Domain\Research\Actions\CreateResearchRun;
use App\Domain\Research\Enums\SearchOrder;
use App\Models\Channel;
use App\Models\ChannelSnapshot;
use App\Models\Market;
use App\Models\ResearchQuery;
use App\Models\ResearchRun;
use App\Models\ResearchRunVideo;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoSnapshot;
use Carbon\CarbonImmutable;
use Database\Seeders\MarketSeeder;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CatalogSnapshotSchemaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MarketSeeder::class);
    }

    public function test_catalog_snapshot_tables_expose_the_required_columns_and_indexes(): void
    {
        $this->assertTrue(Schema::hasColumns('channels', [
            'provider', 'provider_channel_id', 'title', 'custom_url', 'thumbnail_url', 'country',
        ]));
        $this->assertTrue(Schema::hasColumns('videos', [
            'provider', 'provider_video_id', 'channel_id', 'title', 'thumbnail_url', 'published_at',
            'duration_seconds', 'category_id', 'is_short',
        ]));
        $this->assertTrue(Schema::hasColumns('research_run_videos', [
            'research_run_id', 'video_id', 'result_rank', 'page_number', 'provider_order',
            'matched_query_metadata',
        ]));
        $this->assertTrue(Schema::hasColumns('channel_snapshots', [
            'channel_id', 'research_run_id', 'subscriber_count', 'view_count', 'video_count',
            'subscriber_count_hidden', 'metadata', 'collected_at',
        ]));
        $this->assertTrue(Schema::hasColumns('video_snapshots', [
            'video_id', 'research_run_id', 'view_count', 'like_count', 'comment_count', 'age_seconds',
            'views_per_day', 'views_to_subscribers_ratio', 'collected_at',
        ]));

        $this->assertTrue(Schema::hasIndex('channels', ['provider', 'provider_channel_id'], 'unique'));
        $this->assertTrue(Schema::hasIndex('videos', ['provider', 'provider_video_id'], 'unique'));
        $this->assertTrue(Schema::hasIndex('videos', ['channel_id', 'published_at']));
        $this->assertTrue(Schema::hasIndex('research_run_videos', ['research_run_id', 'video_id'], 'unique'));
        $this->assertTrue(Schema::hasIndex('research_run_videos', ['research_run_id', 'result_rank']));
        $this->assertTrue(Schema::hasIndex('channel_snapshots', ['research_run_id', 'channel_id'], 'unique'));
        $this->assertTrue(Schema::hasIndex('channel_snapshots', ['channel_id', 'collected_at']));
        $this->assertTrue(Schema::hasIndex('video_snapshots', ['research_run_id', 'video_id'], 'unique'));
        $this->assertTrue(Schema::hasIndex('video_snapshots', ['video_id', 'collected_at']));
    }

    public function test_catalog_entities_are_deduplicated_by_provider_and_run_membership_preserves_rank(): void
    {
        [, $run] = $this->newQueryAndRun();
        [$channel, $video] = $this->newCatalogEntities();

        $run->videos()->attach($video->id, [
            'result_rank' => 7,
            'page_number' => 2,
            'provider_order' => 6,
            'matched_query_metadata' => ['matched_query' => 'camera research'],
        ]);

        $membership = ResearchRunVideo::query()->sole();
        $runVideo = $run->videos()->sole();

        $this->assertTrue($video->channel->is($channel));
        $this->assertTrue($membership->researchRun->is($run));
        $this->assertTrue($membership->video->is($video));
        $this->assertSame(7, $membership->result_rank);
        $this->assertSame(['matched_query' => 'camera research'], $membership->matched_query_metadata);
        $this->assertSame(7, $runVideo->pivot->result_rank);

        Channel::query()->create([
            'provider' => 'alternative',
            'provider_channel_id' => $channel->provider_channel_id,
            'title' => 'Same external ID in another provider',
        ]);

        try {
            Channel::query()->create([
                'provider' => $channel->provider,
                'provider_channel_id' => $channel->provider_channel_id,
                'title' => 'Duplicate channel',
            ]);
            $this->fail('A provider channel ID must be globally deduplicated within its provider.');
        } catch (QueryException) {
            $this->assertDatabaseCount('channels', 2);
        }

        try {
            Video::query()->create([
                'provider' => $video->provider,
                'provider_video_id' => $video->provider_video_id,
                'channel_id' => $channel->id,
                'title' => 'Duplicate video',
                'published_at' => '2026-08-01 12:00:00',
            ]);
            $this->fail('A provider video ID must be globally deduplicated within its provider.');
        } catch (QueryException) {
            $this->assertDatabaseCount('videos', 1);
        }

        $this->expectException(QueryException::class);

        $run->videos()->attach($video->id, [
            'result_rank' => 8,
            'page_number' => 2,
            'provider_order' => 7,
        ]);
    }

    public function test_snapshots_preserve_nullable_metrics_and_are_immutable_per_run(): void
    {
        [$query, $firstRun] = $this->newQueryAndRun();
        $secondRun = app(CreateResearchRun::class)->handle($query->user, $query, 25);
        [$channel, $video] = $this->newCatalogEntities();
        $firstCollectedAt = CarbonImmutable::parse('2026-08-08 09:00:00 UTC');
        $secondCollectedAt = CarbonImmutable::parse('2026-08-08 11:00:00 UTC');

        $channelSnapshot = ChannelSnapshot::query()->create([
            'channel_id' => $channel->id,
            'research_run_id' => $firstRun->id,
            'subscriber_count' => null,
            'view_count' => 120000,
            'video_count' => 42,
            'subscriber_count_hidden' => true,
            'metadata' => ['source' => 'channels.list'],
            'collected_at' => $firstCollectedAt,
        ]);
        $firstSnapshot = VideoSnapshot::query()->create([
            'video_id' => $video->id,
            'research_run_id' => $firstRun->id,
            'view_count' => 1000,
            'like_count' => null,
            'comment_count' => null,
            'age_seconds' => 86400,
            'views_per_day' => '1000.000000',
            'views_to_subscribers_ratio' => null,
            'collected_at' => $firstCollectedAt,
        ]);
        $secondSnapshot = VideoSnapshot::query()->create([
            'video_id' => $video->id,
            'research_run_id' => $secondRun->id,
            'view_count' => 1500,
            'like_count' => 90,
            'comment_count' => 12,
            'age_seconds' => 93600,
            'views_per_day' => '1384.615385',
            'views_to_subscribers_ratio' => null,
            'collected_at' => $secondCollectedAt,
        ]);

        $this->assertNull($channelSnapshot->subscriber_count);
        $this->assertTrue($channelSnapshot->subscriber_count_hidden);
        $this->assertNull($firstSnapshot->like_count);
        $this->assertNull($firstSnapshot->views_to_subscribers_ratio);
        $this->assertSame('1000.000000', $firstSnapshot->views_per_day);
        $this->assertSame(1000, $firstSnapshot->view_count);
        $this->assertSame(1500, $secondSnapshot->view_count);
        $this->assertCount(2, $video->snapshots);
        $this->assertCount(1, $firstRun->videoSnapshots);
        $this->assertCount(1, $firstRun->channelSnapshots);

        try {
            ChannelSnapshot::query()->create([
                'channel_id' => $channel->id,
                'research_run_id' => $firstRun->id,
                'subscriber_count' => 9999,
                'collected_at' => $secondCollectedAt,
            ]);
            $this->fail('Only one channel snapshot may exist for a channel in a run.');
        } catch (QueryException) {
            $this->assertDatabaseCount('channel_snapshots', 1);
        }

        try {
            VideoSnapshot::query()->create([
                'video_id' => $video->id,
                'research_run_id' => $firstRun->id,
                'view_count' => 9999,
                'collected_at' => $secondCollectedAt,
            ]);
            $this->fail('Only one video snapshot may exist for a video in a run.');
        } catch (QueryException) {
            $this->assertDatabaseCount('video_snapshots', 2);
        }

        try {
            $channelSnapshot->update(['view_count' => 9999]);
            $this->fail('Collected channel metrics must not be updated.');
        } catch (DomainException) {
            $this->assertSame(120000, $channelSnapshot->fresh()->view_count);
        }

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Collected metric snapshots are immutable.');

        $firstSnapshot->update(['view_count' => 9999]);
    }

    public function test_deleting_a_run_removes_only_its_memberships_and_snapshots(): void
    {
        [, $run] = $this->newQueryAndRun();
        [$channel, $video] = $this->newCatalogEntities();

        $run->videos()->attach($video->id, [
            'result_rank' => 1,
            'page_number' => 1,
            'provider_order' => 0,
        ]);
        ChannelSnapshot::query()->create([
            'channel_id' => $channel->id,
            'research_run_id' => $run->id,
            'subscriber_count_hidden' => true,
            'collected_at' => '2026-08-08 09:00:00',
        ]);
        VideoSnapshot::query()->create([
            'video_id' => $video->id,
            'research_run_id' => $run->id,
            'collected_at' => '2026-08-08 09:00:00',
        ]);

        try {
            $video->delete();
            $this->fail('A catalog video referenced by run history must be preserved.');
        } catch (QueryException) {
            $this->assertDatabaseHas('videos', ['id' => $video->id]);
        }

        $run->delete();

        $this->assertDatabaseCount('research_run_videos', 0);
        $this->assertDatabaseCount('channel_snapshots', 0);
        $this->assertDatabaseCount('video_snapshots', 0);
        $this->assertDatabaseHas('channels', ['id' => $channel->id]);
        $this->assertDatabaseHas('videos', ['id' => $video->id]);
    }

    /** @return array{ResearchQuery, ResearchRun} */
    private function newQueryAndRun(): array
    {
        $user = User::factory()->create();
        $market = Market::query()->where('key', 'global_en')->firstOrFail();
        $query = app(CreateResearchQuery::class)->handle(
            user: $user,
            market: $market,
            queryText: 'camera research',
            searchOrder: SearchOrder::ViewCount,
        );

        return [$query, app(CreateResearchRun::class)->handle($user, $query, 25)];
    }

    /** @return array{Channel, Video} */
    private function newCatalogEntities(): array
    {
        $channel = Channel::query()->create([
            'provider' => 'youtube',
            'provider_channel_id' => 'channel-1',
            'title' => 'Camera Lab',
            'custom_url' => '@cameralab',
            'thumbnail_url' => 'https://example.test/channel.jpg',
            'country' => 'RO',
        ]);
        $video = Video::query()->create([
            'provider' => 'youtube',
            'provider_video_id' => 'video-1',
            'channel_id' => $channel->id,
            'title' => 'Camera setup',
            'thumbnail_url' => 'https://example.test/video.jpg',
            'published_at' => '2026-08-01 12:00:00',
            'duration_seconds' => 625,
            'category_id' => '26',
            'is_short' => false,
        ]);

        return [$channel, $video];
    }
}
