<?php

namespace Tests\Feature\Retention;

use App\Domain\Exports\Enums\ExportFormat;
use App\Domain\Exports\Enums\ExportStatus;
use App\Domain\Library\Enums\LibraryTargetType;
use App\Domain\Research\Enums\ResearchRunKind;
use App\Domain\Research\Enums\ResearchRunStatus;
use App\Domain\Retention\Actions\CreateCleanupRun;
use App\Domain\Retention\Actions\ExecuteCleanup;
use App\Domain\Retention\Enums\CleanupMode;
use App\Domain\Retention\Enums\CleanupStatus;
use App\Domain\Retention\Enums\CleanupTargetType;
use App\Domain\Retention\Enums\DeletionOutcome;
use App\Domain\Retention\Services\BuildRetentionPlan;
use App\Jobs\Retention\ExecuteCleanupRun;
use App\Models\Channel;
use App\Models\ChannelSnapshot;
use App\Models\Favorite;
use App\Models\Market;
use App\Models\ResearchExport;
use App\Models\ResearchProject;
use App\Models\ResearchQuery;
use App\Models\ResearchRun;
use App\Models\SnapshotDeletionItem;
use App\Models\Tag;
use App\Models\Taggable;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoSnapshot;
use Database\Seeders\MarketSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

final class RetentionAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MarketSeeder::class);
        Date::setTestNow('2026-08-08 12:00:00');
        Queue::fake();
        Storage::fake('local');
    }

    public function test_month_end_cutoff_uses_calendar_months_and_excludes_the_exact_boundary(): void
    {
        Date::setTestNow('2026-08-31 12:00:00');
        $owner = User::factory()->create();
        $older = $this->makeRun($owner, 'One second older', '2026-02-28 11:59:59');
        $exact = $this->makeRun($owner, 'Exact cutoff', '2026-02-28 12:00:00');
        $newer = $this->makeRun($owner, 'One second newer', '2026-02-28 12:00:01');

        $plan = app(BuildRetentionPlan::class)->handle($owner);

        $this->assertSame('2026-02-28T12:00:00+00:00', $plan->cutoffAt->toIso8601String());
        $this->assertSame([$older->public_id], collect($plan->runs)->pluck('public_id')->all());
        $this->assertNotContains($exact->public_id, collect($plan->runs)->pluck('public_id')->all());
        $this->assertNotContains($newer->public_id, collect($plan->runs)->pluck('public_id')->all());
    }

    public function test_cleanup_preserves_user_preferences_projects_queries_library_records_and_catalog_entities(): void
    {
        $owner = User::factory()->create([
            'timezone' => 'Europe/Bucharest',
            'default_market_key' => 'ro_ro',
            'default_result_depth' => 100,
        ]);
        $project = ResearchProject::query()->create([
            'user_id' => $owner->id,
            'name' => 'Preserved project',
        ]);
        $run = $this->makeRun($owner, 'Preserved query', '2025-01-01 12:00:00', $project);
        [$channel, $video] = $this->attachSnapshots($run);
        $favorite = Favorite::query()->create([
            'user_id' => $owner->id,
            'research_project_id' => $project->id,
            'target_type' => LibraryTargetType::ResearchQuery,
            'target_id' => $run->research_query_id,
            'note' => 'Keep the saved query',
        ]);
        $tag = Tag::query()->create([
            'user_id' => $owner->id,
            'name' => 'Durable',
            'name_key' => 'durable',
        ]);
        $taggable = Taggable::query()->create([
            'tag_id' => $tag->id,
            'target_type' => LibraryTargetType::ResearchQuery,
            'target_id' => $run->research_query_id,
        ]);
        $cleanup = app(CreateCleanupRun::class)->handle($owner, CleanupMode::ManualRetention, false, initiator: $owner);

        (new ExecuteCleanupRun($cleanup->id))->handle(app(ExecuteCleanup::class));

        $this->assertDatabaseMissing('research_runs', ['id' => $run->id]);
        $this->assertDatabaseHas('users', [
            'id' => $owner->id,
            'timezone' => 'Europe/Bucharest',
            'default_market_key' => 'ro_ro',
            'default_result_depth' => 100,
        ]);
        $this->assertDatabaseHas('research_projects', ['id' => $project->id]);
        $this->assertDatabaseHas('research_queries', ['id' => $run->research_query_id]);
        $this->assertDatabaseHas('favorites', ['id' => $favorite->id]);
        $this->assertDatabaseHas('tags', ['id' => $tag->id]);
        $this->assertDatabaseHas('taggables', ['id' => $taggable->id]);
        $this->assertDatabaseHas('channels', ['id' => $channel->id]);
        $this->assertDatabaseHas('videos', ['id' => $video->id]);
        $this->assertDatabaseMissing('video_snapshots', ['research_run_id' => $run->id]);
        $this->assertDatabaseMissing('channel_snapshots', ['research_run_id' => $run->id]);
    }

    public function test_favorite_added_after_queueing_is_rechecked_and_preserved_at_execution(): void
    {
        $owner = User::factory()->create();
        $run = $this->makeRun($owner, 'Favorite race', '2025-01-01 12:00:00');
        $cleanup = app(CreateCleanupRun::class)->handle($owner, CleanupMode::ManualRetention, false, initiator: $owner);
        $favorite = Favorite::query()->create([
            'user_id' => $owner->id,
            'target_type' => LibraryTargetType::ResearchRun,
            'target_id' => $run->id,
        ]);

        (new ExecuteCleanupRun($cleanup->id))->handle(app(ExecuteCleanup::class));

        $this->assertDatabaseHas('research_runs', ['id' => $run->id]);
        $this->assertDatabaseHas('favorites', ['id' => $favorite->id]);
        $this->assertDatabaseHas('snapshot_deletion_items', [
            'cleanup_run_id' => $cleanup->id,
            'target_reference' => $run->public_id,
            'outcome' => DeletionOutcome::PreservedFavorite->value,
            'favorite_impacted' => true,
        ]);
        $this->assertSame(CleanupStatus::Completed, $cleanup->fresh()->status);
        $this->assertSame([], $cleanup->fresh()->deleted_counts);
    }

    public function test_retry_resumes_only_remaining_eligible_items_after_partial_persistence(): void
    {
        $owner = User::factory()->create();
        $first = $this->makeRun($owner, 'Already deleted before retry', '2025-01-01 12:00:00');
        $second = $this->makeRun($owner, 'Remaining retry target', '2025-01-02 12:00:00');
        $cleanup = app(CreateCleanupRun::class)->handle($owner, CleanupMode::ManualRetention, false, initiator: $owner);
        $firstItem = SnapshotDeletionItem::query()
            ->where('cleanup_run_id', $cleanup->id)
            ->where('target_reference', $first->public_id)
            ->firstOrFail();

        $first->delete();
        $firstItem->update([
            'outcome' => DeletionOutcome::Deleted,
            'deleted_at' => now(),
        ]);
        $cleanup->update([
            'status' => CleanupStatus::Processing,
            'started_at' => now()->subMinute(),
            'deleted_counts' => ['research_runs' => 1],
        ]);

        $job = new ExecuteCleanupRun($cleanup->id);
        $job->handle(app(ExecuteCleanup::class));
        $job->handle(app(ExecuteCleanup::class));

        $completed = $cleanup->fresh();
        $this->assertSame(CleanupStatus::Completed, $completed->status);
        $this->assertSame(2, $completed->deleted_counts['research_runs']);
        $this->assertDatabaseMissing('research_runs', ['id' => $first->id]);
        $this->assertDatabaseMissing('research_runs', ['id' => $second->id]);
        $this->assertDatabaseCount('snapshot_deletion_items', 2);
        $this->assertSame(2, SnapshotDeletionItem::query()
            ->where('cleanup_run_id', $cleanup->id)
            ->where('outcome', DeletionOutcome::Deleted->value)
            ->count());
    }

    public function test_foreign_manual_selection_is_rejected_without_audit_or_queue_dispatch(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $foreign = $this->makeRun($other, 'Foreign selection', '2025-01-01 12:00:00');

        $this->actingAs($owner)
            ->delete(route('retention.runs.destroy'), [
                'research_run_ids' => [$foreign->public_id],
                'confirmation' => true,
                'favorite_impact_confirmed' => true,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('cleanup_runs', ['user_id' => $owner->id]);
        $this->assertDatabaseHas('research_runs', ['id' => $foreign->id]);
        Queue::assertNothingPushed();
    }

    public function test_http_dry_run_audits_actor_scope_counts_and_utc_time_without_mutation(): void
    {
        $owner = User::factory()->create();
        $run = $this->makeRun($owner, 'Audited preview', '2025-01-01 12:00:00');
        $this->attachSnapshots($run);

        $this->actingAs($owner)
            ->post(route('retention.preview'))
            ->assertRedirect(route('retention.index'));

        $cleanup = $owner->cleanupRuns()->with('items')->sole();
        $this->assertSame($owner->id, $cleanup->initiated_by_user_id);
        $this->assertSame(CleanupMode::ManualRetention, $cleanup->mode);
        $this->assertSame(CleanupStatus::Previewed, $cleanup->status);
        $this->assertTrue($cleanup->dry_run);
        $this->assertSame('2026-02-08T12:00:00+00:00', $cleanup->cutoff_at->toIso8601String());
        $this->assertSame('2026-08-08T12:00:00+00:00', $cleanup->completed_at?->toIso8601String());
        $this->assertSame(1, $cleanup->eligible_counts['research_runs']);
        $this->assertSame(1, $cleanup->eligible_counts['video_snapshots']);
        $this->assertSame(1, $cleanup->items->count());
        $item = $cleanup->items->sole();
        $this->assertSame($run->public_id, $item->target_reference);
        $this->assertSame('2025-01-01T12:00:00+00:00', $item->original_collection_at->toIso8601String());
        $this->assertDatabaseHas('research_runs', ['id' => $run->id]);
        Queue::assertNothingPushed();
    }

    public function test_export_that_is_no_longer_expired_is_skipped_and_its_file_is_preserved(): void
    {
        $owner = User::factory()->create();
        $path = "exports/{$owner->id}/state-changed.csv";
        Storage::disk('local')->put($path, 'preserve me');
        $export = ResearchExport::query()->create([
            'user_id' => $owner->id,
            'format' => ExportFormat::Csv,
            'status' => ExportStatus::Completed,
            'selection' => ['type' => 'research_runs', 'research_run_ids' => []],
            'disk' => 'local',
            'path' => $path,
            'size_bytes' => 11,
            'checksum_sha256' => hash('sha256', 'preserve me'),
            'completed_at' => '2026-07-01 12:00:00',
            'expires_at' => '2026-08-01 12:00:00',
        ]);
        $cleanup = app(CreateCleanupRun::class)->handle($owner, CleanupMode::ManualRetention, false, initiator: $owner);
        $export->update(['expires_at' => '2026-08-09 12:00:00']);

        (new ExecuteCleanupRun($cleanup->id))->handle(app(ExecuteCleanup::class));

        $this->assertDatabaseHas('exports', ['id' => $export->id]);
        Storage::disk('local')->assertExists($path);
        $this->assertDatabaseHas('snapshot_deletion_items', [
            'cleanup_run_id' => $cleanup->id,
            'target_type' => CleanupTargetType::Export->value,
            'target_reference' => $export->public_id,
            'outcome' => DeletionOutcome::SkippedIneligible->value,
        ]);
        $this->assertSame([], $cleanup->fresh()->deleted_counts);
    }

    public function test_terminal_job_failure_keeps_targets_and_records_only_safe_retryable_context(): void
    {
        $owner = User::factory()->create();
        $run = $this->makeRun($owner, 'Safe failure target', '2025-01-01 12:00:00');
        $cleanup = app(CreateCleanupRun::class)->handle($owner, CleanupMode::ManualRetention, false, initiator: $owner);
        $job = new ExecuteCleanupRun($cleanup->id);

        $job->failed(new RuntimeException('secret target '.$run->public_id));

        $failed = $cleanup->fresh();
        $this->assertSame(CleanupStatus::Failed, $failed->status);
        $this->assertSame('retention_cleanup_failed', $failed->error_code);
        $this->assertStringContainsString('retried safely', $failed->error_message ?? '');
        $this->assertStringNotContainsString($run->public_id, $failed->error_message ?? '');
        $this->assertDatabaseHas('research_runs', ['id' => $run->id]);
        $this->assertDatabaseHas('snapshot_deletion_items', [
            'cleanup_run_id' => $cleanup->id,
            'target_reference' => $run->public_id,
            'outcome' => DeletionOutcome::Eligible->value,
        ]);
    }

    private function makeRun(
        User $user,
        string $queryText,
        string $completedAt,
        ?ResearchProject $project = null,
    ): ResearchRun {
        $market = Market::query()->where('key', 'global_en')->firstOrFail();
        $query = ResearchQuery::query()->create([
            'user_id' => $user->id,
            'research_project_id' => $project?->id,
            'market_id' => $market->id,
            'query_text' => $queryText,
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
            'parameters' => ['search_order' => 'relevance'],
            'requested_result_count' => 25,
            'collected_result_count' => 1,
            'enriched_result_count' => 1,
            'progress_percent' => 100,
            'completed_at' => $completedAt,
        ]);
    }

    /** @return array{Channel, Video} */
    private function attachSnapshots(ResearchRun $run): array
    {
        $channel = Channel::query()->create([
            'provider' => 'youtube',
            'provider_channel_id' => 'acceptance-channel-'.$run->id,
            'title' => 'Preserved catalog channel',
        ]);
        $video = Video::query()->create([
            'provider' => 'youtube',
            'provider_video_id' => 'acceptance-video-'.$run->id,
            'channel_id' => $channel->id,
            'title' => 'Preserved catalog video',
            'published_at' => '2025-01-01 00:00:00',
        ]);
        $run->videos()->attach($video->id, [
            'result_rank' => 1,
            'page_number' => 1,
            'provider_order' => 1,
        ]);
        VideoSnapshot::query()->create([
            'research_run_id' => $run->id,
            'video_id' => $video->id,
            'view_count' => 100,
            'age_seconds' => 100,
            'views_per_day' => 10,
            'collected_at' => $run->completed_at,
        ]);
        ChannelSnapshot::query()->create([
            'research_run_id' => $run->id,
            'channel_id' => $channel->id,
            'view_count' => 1000,
            'subscriber_count_hidden' => false,
            'collected_at' => $run->completed_at,
        ]);

        return [$channel, $video];
    }
}
