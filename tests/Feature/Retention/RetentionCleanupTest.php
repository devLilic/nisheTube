<?php

namespace Tests\Feature\Retention;

use App\Domain\Collection\Enums\CollectionCachePolicy;
use App\Domain\Collection\Enums\CollectionRunKind;
use App\Domain\Collection\Enums\CollectionRunStatus;
use App\Domain\Exports\Enums\ExportFormat;
use App\Domain\Exports\Enums\ExportStatus;
use App\Domain\Library\Enums\LibraryTargetType;
use App\Domain\Research\Enums\ResearchRunKind;
use App\Domain\Research\Enums\ResearchRunStatus;
use App\Domain\Retention\Actions\CreateCleanupRun;
use App\Domain\Retention\Actions\ExecuteCleanup;
use App\Domain\Retention\Enums\CleanupMode;
use App\Domain\Retention\Enums\CleanupStatus;
use App\Domain\Retention\Enums\DeletionOutcome;
use App\Domain\Retention\Services\BuildRetentionPlan;
use App\Jobs\Retention\ExecuteCleanupRun;
use App\Models\Channel;
use App\Models\ChannelSnapshot;
use App\Models\Favorite;
use App\Models\Market;
use App\Models\OpportunityScore;
use App\Models\ResearchExport;
use App\Models\ResearchQuery;
use App\Models\ResearchRun;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoSnapshot;
use Database\Seeders\MarketSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class RetentionCleanupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MarketSeeder::class);
        Date::setTestNow('2026-08-08 12:00:00');
        Storage::fake('local');
    }

    public function test_cleanup_audit_schema_and_exact_six_month_eligibility_are_stable(): void
    {
        $this->assertTrue(Schema::hasColumns('cleanup_runs', [
            'public_id', 'user_id', 'initiated_by_user_id', 'mode', 'status', 'cutoff_at',
            'dry_run', 'eligible_counts', 'deleted_counts', 'started_at', 'completed_at',
            'failed_at', 'error_code', 'error_message',
        ]));
        $this->assertTrue(Schema::hasColumns('snapshot_deletion_items', [
            'cleanup_run_id', 'target_type', 'target_reference', 'original_collection_at',
            'outcome', 'favorite_impacted', 'deleted_at',
        ]));

        $owner = User::factory()->create();
        $eligible = $this->makeRun($owner, 'Old completed', ResearchRunStatus::Completed, '2026-02-08 11:59:59');
        $this->snapshot($eligible);
        $boundary = $this->makeRun($owner, 'Exact boundary', ResearchRunStatus::Completed, '2026-02-08 12:00:00');
        $failed = $this->makeRun($owner, 'Old failed', ResearchRunStatus::Failed, '2026-02-07 12:00:00');
        $preserved = $this->makeRun($owner, 'Saved favorite', ResearchRunStatus::Completed, '2026-01-01 12:00:00');
        Favorite::query()->create([
            'user_id' => $owner->id,
            'target_type' => LibraryTargetType::ResearchRun,
            'target_id' => $preserved->id,
        ]);

        $plan = app(BuildRetentionPlan::class)->handle($owner);

        $this->assertSame('2026-02-08T12:00:00+00:00', $plan->cutoffAt->toIso8601String());
        $this->assertSame(2, $plan->counts['research_runs']);
        $this->assertSame(1, $plan->counts['video_snapshots']);
        $this->assertSame(1, $plan->counts['channel_snapshots']);
        $this->assertSame(1, $plan->counts['preserved_favorites']);
        $this->assertEqualsCanonicalizing(
            [$eligible->public_id, $failed->public_id, $preserved->public_id],
            collect($plan->runs)->pluck('public_id')->all(),
        );
        $this->assertNotContains($boundary->public_id, collect($plan->runs)->pluck('public_id')->all());
    }

    public function test_dry_run_records_auditable_preview_without_queueing_or_deleting(): void
    {
        Queue::fake();
        $owner = User::factory()->create();
        $eligible = $this->makeRun($owner, 'Preview only', ResearchRunStatus::Completed, '2025-12-01 12:00:00');

        $cleanup = app(CreateCleanupRun::class)->handle(
            $owner,
            CleanupMode::ManualRetention,
            dryRun: true,
            initiator: $owner,
        );

        $this->assertSame(CleanupStatus::Previewed, $cleanup->status);
        $this->assertTrue($cleanup->dry_run);
        $this->assertSame($owner->id, $cleanup->initiated_by_user_id);
        $this->assertSame(1, $cleanup->eligible_counts['research_runs']);
        $this->assertDatabaseHas('snapshot_deletion_items', [
            'cleanup_run_id' => $cleanup->id,
            'target_reference' => $eligible->public_id,
            'outcome' => DeletionOutcome::Eligible->value,
        ]);
        $this->assertDatabaseHas('research_runs', ['id' => $eligible->id]);
        Queue::assertNothingPushed();
    }

    public function test_retention_execution_is_dependency_safe_preserves_favorites_and_is_idempotent(): void
    {
        Queue::fake();
        $owner = User::factory()->create();
        $eligible = $this->makeRun($owner, 'Delete old', ResearchRunStatus::Completed, '2025-12-01 12:00:00');
        $this->snapshot($eligible);
        $preserved = $this->makeRun($owner, 'Keep saved', ResearchRunStatus::Completed, '2025-11-01 12:00:00');
        $favorite = Favorite::query()->create([
            'user_id' => $owner->id,
            'target_type' => LibraryTargetType::ResearchRun,
            'target_id' => $preserved->id,
            'note' => 'Keep this evidence',
        ]);
        $exportPath = "exports/{$owner->id}/expired.csv";
        Storage::disk('local')->put($exportPath, 'expired export');
        $export = ResearchExport::query()->create([
            'user_id' => $owner->id,
            'format' => ExportFormat::Csv,
            'status' => ExportStatus::Completed,
            'selection' => ['type' => 'research_runs', 'research_run_ids' => [$eligible->public_id]],
            'disk' => 'local',
            'path' => $exportPath,
            'size_bytes' => 14,
            'checksum_sha256' => hash('sha256', 'expired export'),
            'completed_at' => '2026-07-01 12:00:00',
            'expires_at' => '2026-07-08 12:00:00',
        ]);
        $cleanup = app(CreateCleanupRun::class)->handle($owner, CleanupMode::ManualRetention, false, initiator: $owner);
        $job = new ExecuteCleanupRun($cleanup->id);

        $job->handle(app(ExecuteCleanup::class));
        $job->handle(app(ExecuteCleanup::class));

        $completed = $cleanup->fresh();
        $this->assertSame(CleanupStatus::Completed, $completed->status);
        $this->assertSame(1, $completed->deleted_counts['research_runs']);
        $this->assertSame(1, $completed->deleted_counts['video_snapshots']);
        $this->assertSame(1, $completed->deleted_counts['expired_exports']);
        $this->assertDatabaseMissing('research_runs', ['id' => $eligible->id]);
        $this->assertDatabaseMissing('video_snapshots', ['research_run_id' => $eligible->id]);
        $this->assertDatabaseMissing('channel_snapshots', ['research_run_id' => $eligible->id]);
        $this->assertDatabaseHas('research_queries', ['id' => $eligible->research_query_id]);
        $this->assertDatabaseHas('research_runs', ['id' => $preserved->id]);
        $this->assertDatabaseHas('favorites', ['id' => $favorite->id]);
        $this->assertDatabaseMissing('exports', ['id' => $export->id]);
        Storage::disk('local')->assertMissing($exportPath);
        $this->assertDatabaseCount('cleanup_runs', 1);
    }

    public function test_manual_selection_requires_owned_terminal_runs_and_explicit_favorite_confirmation(): void
    {
        Queue::fake();
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $saved = $this->makeRun($owner, 'Recent saved run', ResearchRunStatus::Completed, '2026-08-07 12:00:00');
        $foreign = $this->makeRun($other, 'Foreign run', ResearchRunStatus::Completed, '2025-01-01 12:00:00');
        Favorite::query()->create([
            'user_id' => $owner->id,
            'target_type' => LibraryTargetType::ResearchRun,
            'target_id' => $saved->id,
        ]);

        try {
            app(CreateCleanupRun::class)->handle(
                $owner,
                CleanupMode::ManualSelection,
                false,
                [$foreign->public_id],
                true,
                $owner,
            );
            $this->fail('A foreign research run was accepted for cleanup.');
        } catch (AuthorizationException) {
            $this->addToAssertionCount(1);
        }

        $this->expectExceptionMessage('Confirm that deleting the selected run will also remove its favorite.');
        app(CreateCleanupRun::class)->handle(
            $owner,
            CleanupMode::ManualSelection,
            false,
            [$saved->public_id],
            false,
            $owner,
        );
    }

    public function test_confirmed_manual_selection_deletes_favorite_and_snapshot_but_preserves_query(): void
    {
        Queue::fake();
        $owner = User::factory()->create();
        $saved = $this->makeRun($owner, 'Explicit delete', ResearchRunStatus::Completed, '2026-08-07 12:00:00');
        Favorite::query()->create([
            'user_id' => $owner->id,
            'target_type' => LibraryTargetType::ResearchRun,
            'target_id' => $saved->id,
        ]);
        $cleanup = app(CreateCleanupRun::class)->handle(
            $owner,
            CleanupMode::ManualSelection,
            false,
            [$saved->public_id],
            true,
            $owner,
        );

        (new ExecuteCleanupRun($cleanup->id))->handle(app(ExecuteCleanup::class));

        $this->assertDatabaseMissing('research_runs', ['id' => $saved->id]);
        $this->assertDatabaseMissing('favorites', [
            'target_type' => LibraryTargetType::ResearchRun->value,
            'target_id' => $saved->id,
        ]);
        $this->assertDatabaseHas('research_queries', ['id' => $saved->research_query_id]);
        $this->assertDatabaseHas('snapshot_deletion_items', [
            'cleanup_run_id' => $cleanup->id,
            'target_reference' => $saved->public_id,
            'outcome' => DeletionOutcome::Deleted->value,
            'favorite_impacted' => true,
        ]);
    }

    public function test_retention_settings_are_visible_owner_scoped_and_protected(): void
    {
        Queue::fake();
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $run = $this->makeRun($owner, 'Visible cleanup target', ResearchRunStatus::Completed, '2025-12-01 12:00:00');
        app(CreateCleanupRun::class)->handle($other, CleanupMode::ManualRetention, true, initiator: $other);

        $this->get(route('retention.index'))->assertRedirect(route('login'));

        $this->actingAs($owner)
            ->get(route('retention.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/retention')
                ->missing('retention')
                ->loadDeferredProps('default', fn (Assert $deferred): Assert => $deferred
                    ->where('retention.preview.counts.research_runs', 1)
                    ->where('retention.preview.runs.0.public_id', $run->public_id)
                    ->where('retention.preview.runs.0.artifacts', 0)
                    ->where('retention.has_active', false)
                    ->has('retention.history', 0)));

        $this->actingAs($owner)
            ->delete(route('retention.runs.destroy'), [
                'research_run_ids' => [$run->public_id],
                'confirmation' => true,
            ])
            ->assertRedirect(route('retention.index'));

        $this->assertDatabaseHas('cleanup_runs', [
            'user_id' => $owner->id,
            'mode' => CleanupMode::ManualSelection->value,
            'status' => CleanupStatus::Queued->value,
        ]);
        Queue::assertPushed(ExecuteCleanupRun::class);
    }

    public function test_manual_artisan_dry_run_records_each_user_without_deleting_or_queueing(): void
    {
        Queue::fake();
        $first = User::factory()->create();
        $second = User::factory()->create();
        $firstRun = $this->makeRun($first, 'First account', ResearchRunStatus::Completed, '2025-01-01 12:00:00');
        $secondRun = $this->makeRun($second, 'Second account', ResearchRunStatus::Failed, '2025-01-02 12:00:00');

        $this->assertSame(0, Artisan::call('retention:cleanup', ['--dry-run' => true]));
        $this->assertStringContainsString(
            'Retention cleanup previewed for 2 user(s).',
            Artisan::output(),
        );

        $this->assertDatabaseCount('cleanup_runs', 2);
        $this->assertDatabaseHas('cleanup_runs', [
            'user_id' => $first->id,
            'mode' => CleanupMode::Scheduled->value,
            'status' => CleanupStatus::Previewed->value,
            'dry_run' => true,
        ]);
        $this->assertDatabaseHas('snapshot_deletion_items', ['target_reference' => $firstRun->public_id]);
        $this->assertDatabaseHas('snapshot_deletion_items', ['target_reference' => $secondRun->public_id]);
        $this->assertDatabaseHas('research_runs', ['id' => $firstRun->id]);
        $this->assertDatabaseHas('research_runs', ['id' => $secondRun->id]);
        Queue::assertNothingPushed();
    }

    public function test_deferred_retention_workspace_exposes_owner_scoped_active_progress_and_artifact_details(): void
    {
        Queue::fake();
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $run = $this->makeRun($owner, 'Active cleanup details', ResearchRunStatus::Completed, '2025-01-01 12:00:00');
        $this->snapshot($run);
        $cleanup = app(CreateCleanupRun::class)->handle(
            $owner,
            CleanupMode::ManualRetention,
            false,
            initiator: $owner,
        );
        app(CreateCleanupRun::class)->handle(
            $other,
            CleanupMode::ManualRetention,
            true,
            initiator: $other,
        );

        $this->actingAs($owner)
            ->get(route('retention.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->missing('retention')
                ->loadDeferredProps('default', fn (Assert $deferred): Assert => $deferred
                    ->where('retention.has_active', true)
                    ->where('retention.preview.runs.0.public_id', $run->public_id)
                    ->where('retention.preview.runs.0.video_snapshots', 1)
                    ->where('retention.preview.runs.0.channel_snapshots', 1)
                    ->where('retention.preview.runs.0.opportunity_scores', 1)
                    ->where('retention.preview.runs.0.artifacts', 4)
                    ->has('retention.history', 1)
                    ->where('retention.history.0.public_id', $cleanup->public_id)
                    ->where('retention.history.0.status', CleanupStatus::Queued->value)
                    ->has('retention.history.0.items', 1)
                    ->where('retention.history.0.items.0.target_reference', $run->public_id)
                    ->where('retention.history.0.items.0.outcome', DeletionOutcome::Eligible->value)));
    }

    public function test_deferred_retention_workspace_exposes_completed_and_failed_audit_outcomes(): void
    {
        Queue::fake();
        $owner = User::factory()->create();
        $completedRun = $this->makeRun($owner, 'Completed cleanup audit', ResearchRunStatus::Completed, '2025-01-01 12:00:00');
        $completedCleanup = app(CreateCleanupRun::class)->handle(
            $owner,
            CleanupMode::ManualSelection,
            false,
            [$completedRun->public_id],
            false,
            $owner,
        );
        (new ExecuteCleanupRun($completedCleanup->id))->handle(app(ExecuteCleanup::class));

        $failedRun = $this->makeRun($owner, 'Failed cleanup audit', ResearchRunStatus::Completed, '2025-02-01 12:00:00');
        $failedCleanup = app(CreateCleanupRun::class)->handle(
            $owner,
            CleanupMode::ManualSelection,
            false,
            [$failedRun->public_id],
            false,
            $owner,
        );
        $failedCleanup->update([
            'status' => CleanupStatus::Failed,
            'failed_at' => now(),
            'error_code' => 'retention_cleanup_failed',
            'error_message' => 'Cleanup failed safely for focused interface coverage.',
        ]);

        $this->actingAs($owner)
            ->get(route('retention.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->loadDeferredProps('default', fn (Assert $deferred): Assert => $deferred
                    ->where('retention.has_active', false)
                    ->has('retention.history', 2)
                    ->where('retention.history.0.status', CleanupStatus::Failed->value)
                    ->where('retention.history.0.error_code', 'retention_cleanup_failed')
                    ->where('retention.history.0.error_message', 'Cleanup failed safely for focused interface coverage.')
                    ->where('retention.history.1.status', CleanupStatus::Completed->value)
                    ->where('retention.history.1.deleted_counts.research_runs', 1)
                    ->where('retention.history.1.items.0.outcome', DeletionOutcome::Deleted->value)));
    }

    public function test_manual_cleanup_preserves_observations_pinned_by_another_research_run(): void
    {
        Queue::fake([ExecuteCleanupRun::class]);
        $user = User::factory()->create();
        $source = $this->makeRun($user, 'Shared source', ResearchRunStatus::Completed, '2026-08-08 10:00:00');
        $this->snapshot($source);
        $dependent = $this->makeRun($user, 'Cached dependent', ResearchRunStatus::Completed, '2026-08-08 11:00:00');
        $sourceMembership = $source->videoMemberships()->sole();
        $dependent->videos()->attach($sourceMembership->video_id, [
            'result_rank' => 1,
            'page_number' => 1,
            'provider_order' => 1,
        ]);
        $dependent->videoMemberships()->sole()->pinSources(
            $sourceMembership->videoSnapshot()->firstOrFail(),
            $sourceMembership->channelSnapshot()->firstOrFail(),
        );

        $plan = app(BuildRetentionPlan::class)->handle(
            $user,
            CleanupMode::ManualSelection,
            [$source->public_id],
        );
        $cleanup = app(CreateCleanupRun::class)->handle(
            user: $user,
            mode: CleanupMode::ManualSelection,
            dryRun: false,
            selectedRunPublicIds: [$source->public_id],
        );

        $this->assertSame(DeletionOutcome::PreservedSharedSource, $plan->items[0]['outcome']);
        $this->assertSame(1, $plan->counts['preserved_shared_sources']);
        $this->assertSame(0, $plan->counts['research_runs']);
        $this->assertSame(
            DeletionOutcome::PreservedSharedSource,
            $cleanup->items()->sole()->outcome,
        );
        $this->assertDatabaseHas('research_runs', ['id' => $source->id]);
        $this->assertSame(
            $sourceMembership->video_snapshot_id,
            $dependent->videoMemberships()->sole()->video_snapshot_id,
        );
    }

    private function makeRun(User $user, string $queryText, ResearchRunStatus $status, string $terminalAt): ResearchRun
    {
        $market = Market::query()->where('key', 'global_en')->firstOrFail();
        $query = ResearchQuery::query()->create([
            'user_id' => $user->id,
            'market_id' => $market->id,
            'query_text' => $queryText,
        ]);
        $collectionRun = $user->collectionRuns()->create([
            'provider' => 'youtube',
            'kind' => CollectionRunKind::SearchEnrichment,
            'status' => $status === ResearchRunStatus::Completed
                ? CollectionRunStatus::Completed
                : CollectionRunStatus::Failed,
            'attempt_number' => 1,
            'frozen_request' => ['query_text' => $queryText],
            'cache_policy' => CollectionCachePolicy::FreshOnly,
            'requested_count' => 25,
            'processed_count' => $status === ResearchRunStatus::Completed ? 1 : 0,
            'progress_percent' => $status === ResearchRunStatus::Completed ? 100 : 75,
            'completed_at' => $status === ResearchRunStatus::Completed ? $terminalAt : null,
            'failed_at' => $status === ResearchRunStatus::Failed ? $terminalAt : null,
        ]);

        return ResearchRun::query()->create([
            'user_id' => $user->id,
            'research_query_id' => $query->id,
            'collection_run_id' => $collectionRun->id,
            'kind' => ResearchRunKind::Search,
            'status' => $status,
            'attempt_number' => 1,
            'query_text' => $queryText,
            'market_key' => $market->key,
            'region_code' => $market->region_code,
            'relevance_language' => $market->relevance_language,
            'parameters' => ['search_order' => 'relevance'],
            'requested_result_count' => 25,
            'collected_result_count' => $status === ResearchRunStatus::Completed ? 1 : 0,
            'enriched_result_count' => $status === ResearchRunStatus::Completed ? 1 : 0,
            'progress_percent' => $status === ResearchRunStatus::Completed ? 100 : 75,
            'completed_at' => $status === ResearchRunStatus::Completed ? $terminalAt : null,
            'failed_at' => $status === ResearchRunStatus::Failed ? $terminalAt : null,
        ]);
    }

    private function snapshot(ResearchRun $run): void
    {
        $channel = Channel::query()->create([
            'provider' => 'youtube',
            'provider_channel_id' => 'retention-channel-'.$run->id,
            'title' => 'Retention creator',
        ]);
        $video = Video::query()->create([
            'provider' => 'youtube',
            'provider_video_id' => 'retention-video-'.$run->id,
            'channel_id' => $channel->id,
            'title' => 'Retention sample',
            'published_at' => '2025-01-01 00:00:00',
        ]);
        $run->videos()->attach($video->id, [
            'result_rank' => 1,
            'page_number' => 1,
            'provider_order' => 1,
        ]);
        $videoSnapshot = VideoSnapshot::query()->create([
            'research_run_id' => $run->id,
            'collection_run_id' => $run->collection_run_id,
            'video_id' => $video->id,
            'view_count' => 100,
            'age_seconds' => 100,
            'views_per_day' => 10,
            'collected_at' => $run->completed_at ?? $run->failed_at,
        ]);
        $channelSnapshot = ChannelSnapshot::query()->create([
            'research_run_id' => $run->id,
            'collection_run_id' => $run->collection_run_id,
            'channel_id' => $channel->id,
            'view_count' => 1000,
            'subscriber_count_hidden' => false,
            'collected_at' => $run->completed_at ?? $run->failed_at,
        ]);
        $run->videoMemberships()->sole()->pinSources($videoSnapshot, $channelSnapshot);
        OpportunityScore::query()->create([
            'research_run_id' => $run->id,
            'formula_version' => 'niche-opportunity-v1',
            'overall_score' => 50,
            'demand_momentum_score' => 50,
            'competition_opportunity_score' => 50,
            'audience_reachability_score' => 50,
            'content_freshness_gap_score' => 50,
            'creator_viability_score' => 50,
            'confidence_score' => 50,
            'sample_size' => 1,
            'input_summary' => [],
            'explanations' => [],
            'warnings' => [],
            'calculated_at' => $run->completed_at ?? $run->failed_at,
        ]);
    }
}
