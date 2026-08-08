<?php

namespace Tests\Feature\Exports;

use App\Domain\Exports\Actions\CreateResearchExport;
use App\Domain\Exports\Actions\DeleteResearchExport;
use App\Domain\Exports\Enums\ExportFormat;
use App\Domain\Exports\Enums\ExportStatus;
use App\Domain\Exports\ReadModels\BuildResearchRunExportDataset;
use App\Domain\Exports\Services\ExportWriterManager;
use App\Domain\Exports\Services\ResearchExportColumns;
use App\Domain\Research\Enums\ResearchRunKind;
use App\Domain\Research\Enums\ResearchRunStatus;
use App\Jobs\Exports\GenerateResearchExport;
use App\Models\Channel;
use App\Models\ChannelSnapshot;
use App\Models\Market;
use App\Models\OpportunityScore;
use App\Models\ResearchExport;
use App\Models\ResearchQuery;
use App\Models\ResearchRun;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoSnapshot;
use Database\Seeders\MarketSeeder;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Phar;
use PharData;
use RuntimeException;
use Tests\TestCase;

class ExportGenerationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MarketSeeder::class);
        Storage::fake('local');
    }

    public function test_owner_can_queue_a_frozen_completed_run_selection_behind_export_policy(): void
    {
        Queue::fake();
        $owner = User::factory()->create();
        $run = $this->completedRun($owner, 'Case românești');

        $export = app(CreateResearchExport::class)->handle(
            $owner,
            ExportFormat::Csv,
            [$run->public_id, $run->public_id],
        );

        $this->assertSame(ExportStatus::Queued, $export->status);
        $this->assertSame(ExportFormat::Csv, $export->format);
        $this->assertSame([
            'type' => 'research_runs',
            'research_run_ids' => [$run->public_id],
            'columns' => app(ResearchExportColumns::class)->all(),
        ], $export->selection);
        $this->assertTrue(Gate::forUser($owner)->allows('view', $export));
        $this->assertTrue(Gate::forUser($owner)->allows('download', $export));
        Queue::assertPushed(
            GenerateResearchExport::class,
            fn (GenerateResearchExport $job): bool => $job->researchExportId === $export->id,
        );
        $this->assertDatabaseHas('exports', [
            'id' => $export->id,
            'user_id' => $owner->id,
            'status' => ExportStatus::Queued->value,
        ]);
    }

    public function test_export_creation_rejects_foreign_incomplete_and_invalid_selections(): void
    {
        Queue::fake();
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $foreign = $this->completedRun($otherUser, 'Private research');
        $active = $this->researchRun($owner, 'Still enriching', ResearchRunStatus::Enriching);

        try {
            app(CreateResearchExport::class)->handle($owner, ExportFormat::Csv, [$foreign->public_id]);
            $this->fail('A foreign research run was accepted for export.');
        } catch (AuthorizationException) {
            $this->addToAssertionCount(1);
        }

        try {
            app(CreateResearchExport::class)->handle($owner, ExportFormat::Csv, [$active->public_id]);
            $this->fail('An incomplete research run was accepted for export.');
        } catch (DomainException $exception) {
            $this->assertSame('Only completed research runs can be exported.', $exception->getMessage());
        }

        $this->expectException(DomainException::class);
        app(CreateResearchExport::class)->handle($owner, ExportFormat::Csv, ['not-a-uuid']);
    }

    public function test_csv_job_exports_snapshot_metadata_safely_and_is_idempotent(): void
    {
        Queue::fake();
        Date::setTestNow('2026-08-08 12:00:00');
        $owner = User::factory()->create();
        $run = $this->completedRun($owner, 'Case mici în România', '=SUM(1,1) Пример');
        $export = app(CreateResearchExport::class)->handle($owner, ExportFormat::Csv, [$run->public_id]);
        $job = new GenerateResearchExport($export->id);

        $job->handle(app(BuildResearchRunExportDataset::class), app(ExportWriterManager::class));

        $completed = $export->fresh();
        $this->assertSame(ExportStatus::Completed, $completed->status);
        $this->assertSame('local', $completed->disk);
        $this->assertSame("exports/{$owner->id}/{$export->public_id}.csv", $completed->path);
        $this->assertSame('2026-08-15T12:00:00+00:00', $completed->expires_at?->toIso8601String());
        Storage::disk('local')->assertExists($completed->path);

        $contents = Storage::disk('local')->get($completed->path);
        $this->assertStringStartsWith("\xEF\xBB\xBF", $contents);
        $this->assertStringContainsString('Score formula version', $contents);
        $this->assertStringContainsString('Video metrics collected at (UTC)', $contents);
        $this->assertStringContainsString('Score warnings', $contents);
        $this->assertStringContainsString('Case mici în România', $contents);
        $this->assertStringContainsString("'=SUM(1,1) Пример", $contents);
        $this->assertStringContainsString('niche-opportunity-v1', $contents);
        $this->assertStringContainsString('small_sample', $contents);
        $this->assertSame(strlen($contents), $completed->size_bytes);
        $this->assertSame(hash('sha256', $contents), $completed->checksum_sha256);

        $checksum = $completed->checksum_sha256;
        $job->handle(app(BuildResearchRunExportDataset::class), app(ExportWriterManager::class));

        $this->assertSame($checksum, $export->fresh()->checksum_sha256);
        $this->assertDatabaseCount('exports', 1);
    }

    public function test_xlsx_job_creates_an_excel_compatible_archive_with_unicode_and_metadata(): void
    {
        Queue::fake();
        $owner = User::factory()->create();
        $run = $this->completedRun($owner, 'Организация дома', 'Видео despre spații mici');
        $export = app(CreateResearchExport::class)->handle($owner, ExportFormat::Xlsx, [$run->public_id]);

        (new GenerateResearchExport($export->id))->handle(
            app(BuildResearchRunExportDataset::class),
            app(ExportWriterManager::class),
        );

        $completed = $export->fresh();
        $this->assertSame(ExportStatus::Completed, $completed->status);
        Storage::disk('local')->assertExists($completed->path);
        $this->assertStringStartsWith('PK', Storage::disk('local')->get($completed->path));

        $inspectionPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'nishetube-xlsx-inspection-'.$export->public_id.'.zip';
        file_put_contents($inspectionPath, Storage::disk('local')->get($completed->path));

        try {
            $archive = new PharData($inspectionPath, 0, null, Phar::ZIP);
            $this->assertTrue(isset($archive['[Content_Types].xml']));
            $this->assertTrue(isset($archive['xl/workbook.xml']));
            $this->assertTrue(isset($archive['xl/worksheets/sheet1.xml']));
            $worksheet = $archive['xl/worksheets/sheet1.xml']->getContent();
            $this->assertStringContainsString('Организация дома', $worksheet);
            $this->assertStringContainsString('Видео despre spații mici', $worksheet);
            $this->assertStringContainsString('niche-opportunity-v1', $worksheet);
            $this->assertStringContainsString('small_sample', $worksheet);
            unset($archive);
        } finally {
            if (is_file($inspectionPath)) {
                unlink($inspectionPath);
            }
        }
    }

    public function test_generation_rechecks_ownership_and_records_only_safe_failure_context(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $foreign = $this->completedRun($otherUser, 'Foreign secret research');
        $export = ResearchExport::query()->create([
            'user_id' => $owner->id,
            'format' => ExportFormat::Csv,
            'status' => ExportStatus::Queued,
            'selection' => [
                'type' => 'research_runs',
                'research_run_ids' => [$foreign->public_id],
            ],
        ]);
        $job = new GenerateResearchExport($export->id);

        try {
            $job->handle(app(BuildResearchRunExportDataset::class), app(ExportWriterManager::class));
            $this->fail('A queued export generated a foreign user\'s research.');
        } catch (AuthorizationException $exception) {
            $job->failed(new RuntimeException('Foreign secret research '.$exception->getMessage()));
        }

        $failed = $export->fresh();
        $this->assertSame(ExportStatus::Failed, $failed->status);
        $this->assertSame('export_generation_failed', $failed->error_code);
        $this->assertStringNotContainsString('Foreign secret research', $failed->error_message);
        $this->assertNull($failed->path);
        $this->assertNull($failed->checksum_sha256);
        Storage::disk('local')->assertDirectoryEmpty('exports');
    }

    public function test_safe_deletion_enforces_ownership_and_removes_file_and_record(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $path = 'exports/'.$owner->id.'/completed.csv';
        Storage::disk('local')->put($path, 'saved export');
        $export = ResearchExport::query()->create([
            'user_id' => $owner->id,
            'format' => ExportFormat::Csv,
            'status' => ExportStatus::Completed,
            'selection' => ['type' => 'research_runs', 'research_run_ids' => []],
            'disk' => 'local',
            'path' => $path,
            'size_bytes' => 12,
            'checksum_sha256' => hash('sha256', 'saved export'),
            'completed_at' => now(),
            'expires_at' => now()->addDays(7),
        ]);

        try {
            app(DeleteResearchExport::class)->handle($otherUser, $export);
            $this->fail('A foreign export was deleted.');
        } catch (AuthorizationException) {
            $this->addToAssertionCount(1);
        }

        Storage::disk('local')->assertExists($path);
        app(DeleteResearchExport::class)->handle($owner, $export);

        Storage::disk('local')->assertMissing($path);
        $this->assertDatabaseMissing('exports', ['id' => $export->id]);
    }

    private function completedRun(User $user, string $queryText, string $videoTitle = 'Compact homes tour'): ResearchRun
    {
        $run = $this->researchRun($user, $queryText, ResearchRunStatus::Completed);
        $channel = Channel::query()->create([
            'provider' => 'youtube',
            'provider_channel_id' => 'channel-'.$run->id,
            'title' => 'Creator România',
        ]);
        $video = Video::query()->create([
            'provider' => 'youtube',
            'provider_video_id' => 'video-'.$run->id,
            'channel_id' => $channel->id,
            'title' => $videoTitle,
            'published_at' => '2026-08-01 09:00:00',
        ]);
        $run->videos()->attach($video->id, [
            'result_rank' => 1,
            'page_number' => 1,
            'provider_order' => 1,
        ]);
        VideoSnapshot::query()->create([
            'research_run_id' => $run->id,
            'video_id' => $video->id,
            'view_count' => 1250,
            'like_count' => 125,
            'comment_count' => 25,
            'age_seconds' => 604800,
            'views_per_day' => 178.571429,
            'views_to_subscribers_ratio' => 1.25,
            'collected_at' => '2026-08-08 10:00:00',
        ]);
        ChannelSnapshot::query()->create([
            'research_run_id' => $run->id,
            'channel_id' => $channel->id,
            'subscriber_count' => 1000,
            'view_count' => 50000,
            'video_count' => 30,
            'subscriber_count_hidden' => false,
            'collected_at' => '2026-08-08 10:00:00',
        ]);
        OpportunityScore::query()->create([
            'research_run_id' => $run->id,
            'formula_version' => 'niche-opportunity-v1',
            'overall_score' => 72,
            'demand_momentum_score' => 75,
            'competition_opportunity_score' => 65,
            'audience_reachability_score' => 80,
            'content_freshness_gap_score' => 70,
            'creator_viability_score' => 68,
            'confidence_score' => 77,
            'sample_size' => 1,
            'input_summary' => [],
            'explanations' => [],
            'warnings' => [['code' => 'small_sample', 'message' => 'Small sample.']],
            'calculated_at' => '2026-08-08 10:05:00',
        ]);

        return $run->fresh();
    }

    private function researchRun(User $user, string $queryText, ResearchRunStatus $status): ResearchRun
    {
        $market = Market::query()->where('key', 'global_en')->firstOrFail();
        $query = ResearchQuery::query()->create([
            'user_id' => $user->id,
            'market_id' => $market->id,
            'query_text' => $queryText,
        ]);

        return ResearchRun::query()->create([
            'user_id' => $user->id,
            'research_query_id' => $query->id,
            'kind' => ResearchRunKind::Search,
            'status' => $status,
            'attempt_number' => 1,
            'query_text' => $query->query_text,
            'market_key' => $market->key,
            'region_code' => $market->region_code,
            'relevance_language' => $market->relevance_language,
            'parameters' => ['search_order' => 'relevance', 'video_duration' => null],
            'requested_result_count' => 25,
            'collected_result_count' => $status === ResearchRunStatus::Completed ? 1 : 0,
            'enriched_result_count' => $status === ResearchRunStatus::Completed ? 1 : 0,
            'progress_percent' => $status === ResearchRunStatus::Completed ? 100 : 70,
            'collection_warnings' => ['youtube_partial_data'],
            'completed_at' => $status === ResearchRunStatus::Completed ? '2026-08-08 10:00:00' : null,
        ]);
    }
}
