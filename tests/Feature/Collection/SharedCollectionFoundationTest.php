<?php

namespace Tests\Feature\Collection;

use App\Domain\Collection\Enums\CollectionRunStatus;
use App\Domain\Exports\ReadModels\BuildResearchRunExportDataset;
use App\Domain\Research\Actions\CreateResearchQuery;
use App\Domain\Research\Actions\CreateResearchRun;
use App\Domain\Retention\Enums\CleanupMode;
use App\Domain\Retention\Services\BuildRetentionPlan;
use App\Domain\Scoring\Actions\BuildScoringInput;
use App\Models\Channel;
use App\Models\ChannelSnapshot;
use App\Models\Market;
use App\Models\ResearchRun;
use App\Models\ResearchRunVideo;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoSnapshot;
use Database\Seeders\MarketSeeder;
use DomainException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SharedCollectionFoundationTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MarketSeeder::class);
    }

    public function test_schema_runtime_creation_and_policy_expose_an_owner_scoped_collection_context(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $run = $this->newRun($owner);
        $collectionRun = $run->collectionRun()->sole();

        $this->assertTrue(Schema::hasColumns('collection_runs', [
            'public_id', 'user_id', 'provider', 'kind', 'status', 'attempt_number',
            'frozen_request', 'cache_policy', 'requested_parts', 'requested_count',
            'processed_count', 'progress_percent', 'warnings', 'error_code',
            'error_message', 'started_at', 'completed_at', 'failed_at',
        ]));
        $this->assertTrue(Schema::hasColumns('research_runs', ['collection_run_id']));
        $this->assertTrue(Schema::hasColumns('video_snapshots', ['collection_run_id']));
        $this->assertTrue(Schema::hasColumns('channel_snapshots', ['collection_run_id']));
        $this->assertTrue(Schema::hasColumns('research_run_videos', [
            'video_snapshot_id', 'channel_snapshot_id',
        ]));
        $this->assertTrue(Schema::hasColumns('api_usage_events', ['collection_run_id']));
        $this->assertTrue(Schema::hasIndex('collection_runs', ['user_id', 'created_at']));
        $this->assertTrue(Schema::hasIndex('video_snapshots', ['collection_run_id', 'video_id'], 'unique'));
        $this->assertTrue(Schema::hasIndex('channel_snapshots', ['collection_run_id', 'channel_id'], 'unique'));

        $this->assertSame($owner->id, $collectionRun->user_id);
        $this->assertSame('youtube', $collectionRun->provider);
        $this->assertSame('search_enrichment', $collectionRun->kind->value);
        $this->assertSame(CollectionRunStatus::Queued, $collectionRun->status);
        $this->assertSame('fresh_only', $collectionRun->cache_policy->value);
        $this->assertSame($run->query_text, $collectionRun->frozen_request['query_text']);
        $this->assertSame($run->parameters, $collectionRun->frozen_request['parameters']);
        $this->assertTrue(Gate::forUser($owner)->allows('view', $collectionRun));
        $this->assertFalse(Gate::forUser($otherUser)->allows('view', $collectionRun));
        $this->assertCount(1, $owner->collectionRuns);
        $this->assertCount(0, $otherUser->collectionRuns);

        $this->expectException(DomainException::class);
        $collectionRun->update(['frozen_request' => ['query_text' => 'changed']]);
    }

    public function test_source_links_are_pinned_once_and_reject_foreign_or_replacement_observations(): void
    {
        $run = $this->completedRunWithSources(User::factory()->create());
        $membership = $run->videoMemberships()->sole();
        $firstVideoSnapshotId = $membership->video_snapshot_id;
        $firstChannelSnapshotId = $membership->channel_snapshot_id;

        $membership->pinSources(
            VideoSnapshot::query()->findOrFail($membership->video_snapshot_id),
            ChannelSnapshot::query()->findOrFail($membership->channel_snapshot_id),
        );
        $persistedMembership = $run->videoMemberships()->sole();
        $this->assertSame($firstVideoSnapshotId, $persistedMembership->video_snapshot_id);
        $this->assertSame($firstChannelSnapshotId, $persistedMembership->channel_snapshot_id);

        $foreignRun = $this->newRun($run->user);
        $replacement = VideoSnapshot::query()->create([
            'video_id' => $membership->video_id,
            'research_run_id' => $foreignRun->id,
            'collection_run_id' => $foreignRun->collection_run_id,
            'view_count' => 999999,
            'collected_at' => '2026-08-08 13:00:00',
        ]);

        try {
            $membership->pinSources($replacement, null);
            $this->fail('A foreign observation must not replace the pinned source.');
        } catch (DomainException) {
            $this->assertSame($firstVideoSnapshotId, $run->videoMemberships()->sole()->video_snapshot_id);
        }

        $this->expectException(DomainException::class);
        $membership->update(['video_snapshot_id' => $replacement->id]);
    }

    public function test_migration_round_trip_backfills_sources_without_changing_research_inputs_or_behavior(): void
    {
        Date::setTestNow('2026-08-10 12:00:00 UTC');
        $owner = User::factory()->create();
        $run = $this->completedRunWithSources($owner);
        $migration = require database_path('migrations/2026_08_08_070000_create_shared_collection_foundation.php');
        $researchBefore = $this->withoutKeys(
            (array) DB::table('research_runs')->where('id', $run->id)->first(),
            ['collection_run_id'],
        );
        $videoBefore = $this->withoutKeys(
            (array) DB::table('video_snapshots')->where('research_run_id', $run->id)->first(),
            ['collection_run_id'],
        );
        $channelBefore = $this->withoutKeys(
            (array) DB::table('channel_snapshots')->where('research_run_id', $run->id)->first(),
            ['collection_run_id'],
        );
        $membershipBefore = $this->withoutKeys(
            (array) DB::table('research_run_videos')->where('research_run_id', $run->id)->first(),
            ['video_snapshot_id', 'channel_snapshot_id'],
        );
        $scoringBefore = serialize(app(BuildScoringInput::class)->handle($run));
        $exportBefore = app(BuildResearchRunExportDataset::class)->handle($owner, [$run->public_id]);
        $retentionBefore = app(BuildRetentionPlan::class)
            ->handle($owner, CleanupMode::ManualSelection, [$run->public_id])
            ->toArray();

        try {
            $migration->down();

            $this->assertFalse(Schema::hasTable('collection_runs'));
            $this->assertFalse(Schema::hasColumn('research_runs', 'collection_run_id'));
            $this->assertSame($researchBefore, (array) DB::table('research_runs')->where('id', $run->id)->first());
            $this->assertSame($videoBefore, (array) DB::table('video_snapshots')->where('research_run_id', $run->id)->first());
            $this->assertSame($channelBefore, (array) DB::table('channel_snapshots')->where('research_run_id', $run->id)->first());
            $this->assertSame($membershipBefore, (array) DB::table('research_run_videos')->where('research_run_id', $run->id)->first());

            $migration->up();
        } catch (\Throwable $exception) {
            if (! Schema::hasTable('collection_runs')) {
                $migration->up();
            }

            throw $exception;
        }

        $run = ResearchRun::query()->findOrFail($run->id);
        $membership = $run->videoMemberships()->sole();

        $this->assertSame($owner->id, $run->collectionRun->user_id);
        $this->assertTrue((bool) $run->collectionRun->safe_metadata['historical_backfill']);
        $this->assertSame($run->collection_run_id, $run->videoSnapshots()->sole()->collection_run_id);
        $this->assertSame($run->collection_run_id, $run->channelSnapshots()->sole()->collection_run_id);
        $this->assertSame($run->videoSnapshots()->sole()->id, $membership->video_snapshot_id);
        $this->assertSame($run->channelSnapshots()->sole()->id, $membership->channel_snapshot_id);
        $this->assertSame($researchBefore, $this->withoutKeys(
            (array) DB::table('research_runs')->where('id', $run->id)->first(),
            ['collection_run_id'],
        ));
        $this->assertSame($videoBefore, $this->withoutKeys(
            (array) DB::table('video_snapshots')->where('research_run_id', $run->id)->first(),
            ['collection_run_id'],
        ));
        $this->assertSame($channelBefore, $this->withoutKeys(
            (array) DB::table('channel_snapshots')->where('research_run_id', $run->id)->first(),
            ['collection_run_id'],
        ));
        $this->assertSame($membershipBefore, $this->withoutKeys(
            (array) DB::table('research_run_videos')->where('research_run_id', $run->id)->first(),
            ['video_snapshot_id', 'channel_snapshot_id'],
        ));
        $this->assertSame($scoringBefore, serialize(app(BuildScoringInput::class)->handle($run)));

        $exportAfter = app(BuildResearchRunExportDataset::class)->handle($owner, [$run->public_id]);
        $this->assertSame($exportBefore->headers, $exportAfter->headers);
        $this->assertSame($exportBefore->rows, $exportAfter->rows);
        $this->assertSame(
            $retentionBefore,
            app(BuildRetentionPlan::class)
                ->handle($owner, CleanupMode::ManualSelection, [$run->public_id])
                ->toArray(),
        );
    }

    public function test_owner_only_provenance_props_cover_ready_loading_partial_and_error_states(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $ready = $this->completedRunWithSources($owner);
        $loading = $this->newRun($owner);
        $partial = $this->partialRun($owner);
        $failed = $this->newRun($owner);

        DB::table('research_runs')->where('id', $failed->id)->update([
            'status' => 'failed',
            'failed_at' => '2026-08-08 14:00:00',
            'error_code' => 'youtube_unavailable',
            'error_message' => 'The provider was unavailable.',
        ]);
        DB::table('collection_runs')->where('id', $failed->collection_run_id)->update([
            'status' => 'failed',
            'failed_at' => '2026-08-08 14:00:00',
            'error_code' => 'youtube_unavailable',
            'error_message' => 'The provider was unavailable.',
        ]);

        $this->actingAs($owner)->get(route('research.runs.show', $ready))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('run.provenance.state', 'ready')
                ->where('run.provenance.source.provider', 'youtube')
                ->where('run.provenance.source.video_observation_count', 1)
                ->where('run.provenance.source.channel_observation_count', 1)
                ->where('run.provenance.source.pinned_video_count', 1)
                ->where('run.provenance.source.pinned_channel_count', 1)
                ->where('run.provenance.source.groups.0.label', 'YouTube Data')
                ->where('run.provenance.source.groups.1.label', 'Calculated Metrics'));

        $this->actingAs($owner)->get(route('research.runs.show', $loading))
            ->assertInertia(fn (Assert $page): Assert => $page->where('run.provenance.state', 'loading'));
        $this->actingAs($owner)->get(route('research.runs.show', $partial))
            ->assertInertia(fn (Assert $page): Assert => $page->where('run.provenance.state', 'partial'));
        $this->actingAs($owner)->get(route('research.runs.show', $failed))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('run.provenance.state', 'error')
                ->where('run.provenance.message', 'The provider was unavailable.'));

        $this->actingAs($otherUser)->get(route('research.runs.show', $ready))->assertForbidden();
        auth()->logout();
        $this->get(route('research.runs.show', $ready))->assertRedirect(route('login'));
    }

    private function newRun(User $user): ResearchRun
    {
        $query = app(CreateResearchQuery::class)->handle(
            user: $user,
            market: Market::query()->where('key', 'global_en')->firstOrFail(),
            queryText: 'shared collection research',
        );

        return app(CreateResearchRun::class)->handle($user, $query, 25);
    }

    private function completedRunWithSources(User $user): ResearchRun
    {
        $run = $this->newRun($user);
        [$membership, $videoSnapshot, $channelSnapshot] = $this->sources($run, includeChannel: true);
        $membership->pinSources($videoSnapshot, $channelSnapshot);

        DB::table('research_runs')->where('id', $run->id)->update([
            'status' => 'completed',
            'collected_result_count' => 1,
            'enriched_result_count' => 1,
            'progress_percent' => 100,
            'started_at' => '2026-08-08 11:55:00',
            'search_completed_at' => '2026-08-08 11:58:00',
            'completed_at' => '2026-08-08 12:01:00',
        ]);
        DB::table('collection_runs')->where('id', $run->collection_run_id)->update([
            'status' => 'completed',
            'processed_count' => 1,
            'progress_percent' => 100,
            'started_at' => '2026-08-08 11:55:00',
            'completed_at' => '2026-08-08 12:00:00',
        ]);

        return $run->fresh();
    }

    private function partialRun(User $user): ResearchRun
    {
        $run = $this->newRun($user);
        [$membership, $videoSnapshot] = $this->sources($run, includeChannel: false);
        $membership->pinSources($videoSnapshot, null);

        DB::table('research_runs')->where('id', $run->id)->update([
            'status' => 'completed',
            'collected_result_count' => 1,
            'enriched_result_count' => 1,
            'progress_percent' => 100,
            'completed_at' => '2026-08-08 12:01:00',
        ]);
        DB::table('collection_runs')->where('id', $run->collection_run_id)->update([
            'status' => 'completed',
            'processed_count' => 1,
            'progress_percent' => 100,
            'completed_at' => '2026-08-08 12:00:00',
        ]);

        return $run->fresh();
    }

    /** @return array{ResearchRunVideo, VideoSnapshot, ChannelSnapshot|null} */
    private function sources(ResearchRun $run, bool $includeChannel): array
    {
        $channel = Channel::query()->create([
            'provider' => 'youtube',
            'provider_channel_id' => 'channel-'.$run->id,
            'title' => 'Shared source channel',
        ]);
        $video = Video::query()->create([
            'provider' => 'youtube',
            'provider_video_id' => 'video-'.$run->id,
            'channel_id' => $channel->id,
            'title' => 'Shared source video',
            'published_at' => '2026-08-01 12:00:00',
        ]);
        $membership = ResearchRunVideo::query()->create([
            'research_run_id' => $run->id,
            'video_id' => $video->id,
            'result_rank' => 7,
            'page_number' => 2,
            'provider_order' => 6,
            'matched_query_metadata' => ['source' => 'search.list'],
        ]);
        $channelSnapshot = $includeChannel ? ChannelSnapshot::query()->create([
            'channel_id' => $channel->id,
            'research_run_id' => $run->id,
            'collection_run_id' => $run->collection_run_id,
            'subscriber_count' => 500,
            'view_count' => 50000,
            'video_count' => 40,
            'subscriber_count_hidden' => false,
            'metadata' => ['published_at' => '2020-01-01T00:00:00Z'],
            'collected_at' => '2026-08-08 12:00:00',
        ]) : null;
        $videoSnapshot = VideoSnapshot::query()->create([
            'video_id' => $video->id,
            'research_run_id' => $run->id,
            'collection_run_id' => $run->collection_run_id,
            'view_count' => 1000,
            'like_count' => 80,
            'comment_count' => 20,
            'age_seconds' => 604800,
            'views_per_day' => '142.857143',
            'views_to_subscribers_ratio' => '2.00000000',
            'collected_at' => '2026-08-08 12:00:00',
        ]);

        return [$membership, $videoSnapshot, $channelSnapshot];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $keys
     * @return array<string, mixed>
     */
    private function withoutKeys(array $row, array $keys): array
    {
        foreach ($keys as $key) {
            unset($row[$key]);
        }

        return $row;
    }
}
