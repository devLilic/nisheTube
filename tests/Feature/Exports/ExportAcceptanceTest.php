<?php

namespace Tests\Feature\Exports;

use App\Domain\Exports\Data\ExportDataset;
use App\Domain\Exports\Enums\ExportFormat;
use App\Domain\Exports\Enums\ExportStatus;
use App\Domain\Exports\ReadModels\BuildResearchRunExportDataset;
use App\Domain\Exports\Services\ExportWriterManager;
use App\Domain\Exports\Services\ResearchExportColumns;
use App\Domain\Exports\Writers\CsvExportWriter;
use App\Domain\Exports\Writers\XlsxExportWriter;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Phar;
use PharData;
use RuntimeException;
use Tests\TestCase;

class ExportAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MarketSeeder::class);
        Storage::fake('local');
        Queue::fake();
    }

    public function test_export_routes_enforce_guest_and_cross_user_authorization(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $run = $this->completedRun($owner);
        $ready = $this->export($owner, ExportStatus::Completed, [$run->public_id], 'exports/'.$owner->id.'/ready.csv');
        $failed = $this->export($owner, ExportStatus::Failed, [$run->public_id]);

        Storage::disk('local')->put($ready->path, 'private export');

        $payload = [
            'format' => 'csv',
            'research_run_ids' => [$run->public_id],
            'columns' => app(ResearchExportColumns::class)->all(),
        ];

        $this->post(route('exports.store'), $payload)->assertRedirect(route('login'));
        $this->get(route('exports.download', $ready))->assertRedirect(route('login'));
        $this->post(route('exports.retry', $failed))->assertRedirect(route('login'));
        $this->delete(route('exports.destroy', $ready))->assertRedirect(route('login'));

        $this->actingAs($other)->post(route('exports.store'), $payload)->assertForbidden();
        $this->actingAs($other)->get(route('exports.download', $ready))->assertForbidden();
        $this->actingAs($other)->post(route('exports.retry', $failed))->assertForbidden();
        $this->actingAs($other)->delete(route('exports.destroy', $ready))->assertForbidden();

        $this->assertSame(ExportStatus::Failed, $failed->fresh()->status);
        $this->assertDatabaseHas('exports', ['id' => $ready->id]);
        Storage::disk('local')->assertExists($ready->path);
        Queue::assertNothingPushed();
    }

    public function test_csv_and_xlsx_writers_preserve_unicode_and_neutralize_spreadsheet_payloads(): void
    {
        $dataset = new ExportDataset(
            ['Equals', 'Plus', 'Minus', 'At', 'Quoted Unicode', 'XML'],
            [[
                '=SUM(1,1)',
                " \t+CMD|' /C calc'!A0",
                '-10+20',
                '@danger',
                "Știință, \"casă\"\nПример 日本語",
                "A&B <tag> \"quoted\"\x01",
            ]],
        );
        $csvPath = $this->temporaryPath('csv');
        $xlsxPath = $this->temporaryPath('zip');

        try {
            app(CsvExportWriter::class)->write($dataset, $csvPath);
            $handle = fopen($csvPath, 'rb');
            $this->assertNotFalse($handle);

            try {
                $headers = fgetcsv($handle, escape: '');
                $row = fgetcsv($handle, escape: '');
            } finally {
                fclose($handle);
            }

            $this->assertIsArray($headers);
            $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]) ?? $headers[0];
            $this->assertSame($dataset->headers, $headers);
            $this->assertSame([
                "'=SUM(1,1)",
                "' \t+CMD|' /C calc'!A0",
                "'-10+20",
                "'@danger",
                "Știință, \"casă\"\nПример 日本語",
                "A&B <tag> \"quoted\"\x01",
            ], $row);

            app(XlsxExportWriter::class)->write($dataset, $xlsxPath);
            $archive = new PharData($xlsxPath, 0, null, Phar::ZIP);
            $worksheet = $archive['xl/worksheets/sheet1.xml']->getContent();

            $this->assertStringNotContainsString('<f>', $worksheet);
            $this->assertStringContainsString('<t>=SUM(1,1)</t>', $worksheet);
            $this->assertStringContainsString('Știință, &quot;casă&quot;', $worksheet);
            $this->assertStringContainsString('Пример 日本語', $worksheet);
            $this->assertStringContainsString('A&amp;B &lt;tag&gt; &quot;quoted&quot;', $worksheet);
            $this->assertStringNotContainsString("\x01", $worksheet);
            unset($archive);
        } finally {
            foreach ([$csvPath, $xlsxPath] as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
        }
    }

    public function test_dataset_contains_exact_utc_timestamps_unicode_and_score_metadata(): void
    {
        $owner = User::factory()->create();
        $run = $this->completedRun($owner, 1, 'Case mici în România', '=Idei sigure — Пример 日本語');
        $columns = [
            'run_id', 'query', 'market', 'run_completed_at', 'score_formula', 'score_calculated_at',
            'opportunity_score', 'confidence_score', 'score_warnings', 'video_title', 'video_published_at',
            'video_metrics_collected_at', 'channel_title', 'channel_metrics_collected_at',
        ];

        $dataset = app(BuildResearchRunExportDataset::class)->handle($owner, [$run->public_id], $columns);

        $this->assertSame([
            'Run ID', 'Query', 'Market', 'Run completed at (UTC)', 'Score formula version',
            'Score calculated at (UTC)', 'Opportunity score', 'Confidence score', 'Score warnings',
            'Video title', 'Video published at (UTC)', 'Video metrics collected at (UTC)',
            'Channel title', 'Channel metrics collected at (UTC)',
        ], $dataset->headers);
        $this->assertSame([
            $run->public_id,
            'Case mici în România',
            'global_en',
            '2026-08-08T10:00:00+00:00',
            'niche-opportunity-v1',
            '2026-08-08T10:05:00+00:00',
            72.25,
            77.5,
            '[{"code":"small_sample","message":"Eșantion mic — пример."}]',
            '=Idei sigure — Пример 日本語',
            '2026-08-01T09:00:00+00:00',
            '2026-08-08T10:00:00+00:00',
            'Creator România — Канал',
            '2026-08-08T10:01:00+00:00',
        ], $dataset->rows[0]);
    }

    public function test_large_queued_export_writes_every_video_row_and_completes(): void
    {
        Date::setTestNow('2026-08-08 12:00:00');
        $owner = User::factory()->create();
        $videoCount = 250;
        $run = $this->completedRun($owner, $videoCount, 'Large deterministic export');
        $export = $this->export($owner, ExportStatus::Queued, [$run->public_id]);

        $job = new GenerateResearchExport($export->id);
        $this->assertSame(180, $job->timeout);
        $this->assertSame(3, $job->tries);

        $job->handle(app(BuildResearchRunExportDataset::class), app(ExportWriterManager::class));

        $completed = $export->fresh();
        $this->assertSame(ExportStatus::Completed, $completed->status);
        $this->assertNotNull($completed->path);
        $contents = Storage::disk('local')->get($completed->path);
        $this->assertSame($videoCount + 1, substr_count($contents, "\r\n"));
        $this->assertStringContainsString('Video 250 — Știință Пример', $contents);
        $this->assertSame(strlen($contents), $completed->size_bytes);
        $this->assertSame(hash('sha256', $contents), $completed->checksum_sha256);
    }

    public function test_failed_generation_removes_a_partial_target_and_records_retryable_safe_state(): void
    {
        $owner = User::factory()->create();
        $run = $this->completedRun($owner);
        $export = ResearchExport::query()->create([
            'user_id' => $owner->id,
            'format' => ExportFormat::Csv,
            'status' => ExportStatus::Queued,
            'selection' => [
                'type' => 'research_runs',
                'research_run_ids' => [$run->public_id],
                'columns' => ['unsupported_column'],
            ],
        ]);
        $path = "exports/{$owner->id}/{$export->public_id}.csv";
        Storage::disk('local')->put($path, 'partial private export');
        $job = new GenerateResearchExport($export->id);

        try {
            $job->handle(app(BuildResearchRunExportDataset::class), app(ExportWriterManager::class));
            $this->fail('Invalid stored columns unexpectedly generated an export.');
        } catch (DomainException $exception) {
            $this->assertSame('The export contains an unsupported column selection.', $exception->getMessage());
            $job->failed(new RuntimeException('Sensitive partial private export '.$exception->getMessage()));
        }

        Storage::disk('local')->assertMissing($path);
        $failed = $export->fresh();
        $this->assertSame(ExportStatus::Failed, $failed->status);
        $this->assertSame('export_generation_failed', $failed->error_code);
        $this->assertStringNotContainsString('Sensitive partial private export', $failed->error_message);
        $this->assertNull($failed->disk);
        $this->assertNull($failed->path);
        $this->assertNull($failed->checksum_sha256);
        $this->assertNotNull($failed->failed_at);
    }

    public function test_download_returns_the_expected_file_and_rejects_unready_expired_or_missing_files(): void
    {
        Date::setTestNow('2026-08-08 12:00:00');
        $owner = User::factory()->create();
        $path = 'exports/'.$owner->id.'/download.csv';
        $contents = "\xEF\xBB\xBFQuery\r\nȘtiință\r\n";
        Storage::disk('local')->put($path, $contents);
        $ready = $this->export($owner, ExportStatus::Completed, [], $path, '2026-08-15 12:00:00');
        $queued = $this->export($owner, ExportStatus::Queued, []);
        $expired = $this->export($owner, ExportStatus::Completed, [], $path, '2026-08-08 11:59:59');
        $missing = $this->export($owner, ExportStatus::Completed, [], 'exports/'.$owner->id.'/missing.csv', '2026-08-15 12:00:00');

        $response = $this->actingAs($owner)->get(route('exports.download', $ready));
        $response->assertOk()->assertDownload('nishetube-export-20260808-120000.csv');
        $this->assertSame($contents, $response->streamedContent());

        $this->actingAs($owner)->get(route('exports.download', $queued))->assertConflict();
        $this->actingAs($owner)->get(route('exports.download', $expired))->assertGone();
        $this->actingAs($owner)->get(route('exports.download', $missing))->assertNotFound();
    }

    private function completedRun(
        User $user,
        int $videoCount = 1,
        string $queryText = 'Export acceptance query',
        ?string $firstVideoTitle = null,
    ): ResearchRun {
        $market = Market::query()->where('key', 'global_en')->firstOrFail();
        $query = ResearchQuery::query()->create([
            'user_id' => $user->id,
            'market_id' => $market->id,
            'query_text' => $queryText,
        ]);
        $run = ResearchRun::query()->create([
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
            'requested_result_count' => $videoCount,
            'collected_result_count' => $videoCount,
            'enriched_result_count' => $videoCount,
            'progress_percent' => 100,
            'collection_warnings' => ['youtube_partial_data'],
            'completed_at' => '2026-08-08 10:00:00',
        ]);
        $channel = Channel::query()->create([
            'provider' => 'youtube',
            'provider_channel_id' => 'channel-'.$run->id,
            'title' => 'Creator România — Канал',
        ]);

        $channelSnapshot = ChannelSnapshot::query()->create([
            'research_run_id' => $run->id,
            'channel_id' => $channel->id,
            'subscriber_count' => 1000,
            'view_count' => 50000,
            'video_count' => $videoCount,
            'subscriber_count_hidden' => false,
            'collected_at' => '2026-08-08 10:01:00',
        ]);
        OpportunityScore::query()->create([
            'research_run_id' => $run->id,
            'formula_version' => 'niche-opportunity-v1',
            'overall_score' => 72.25,
            'demand_momentum_score' => 75,
            'competition_opportunity_score' => 65,
            'audience_reachability_score' => 80,
            'content_freshness_gap_score' => 70,
            'creator_viability_score' => 68,
            'confidence_score' => 77.5,
            'sample_size' => $videoCount,
            'input_summary' => [],
            'explanations' => [],
            'warnings' => [['code' => 'small_sample', 'message' => 'Eșantion mic — пример.']],
            'calculated_at' => '2026-08-08 10:05:00',
        ]);

        for ($index = 1; $index <= $videoCount; $index++) {
            $video = Video::query()->create([
                'provider' => 'youtube',
                'provider_video_id' => 'video-'.$run->id.'-'.$index,
                'channel_id' => $channel->id,
                'title' => $index === 1 && $firstVideoTitle !== null
                    ? $firstVideoTitle
                    : "Video {$index} — Știință Пример",
                'published_at' => '2026-08-01 09:00:00',
            ]);
            $videoSnapshot = VideoSnapshot::query()->create([
                'research_run_id' => $run->id,
                'video_id' => $video->id,
                'view_count' => 1000 + $index,
                'like_count' => 100 + $index,
                'comment_count' => 10 + $index,
                'age_seconds' => 604800,
                'views_per_day' => 150 + $index,
                'views_to_subscribers_ratio' => 1 + ($index / 1000),
                'collected_at' => '2026-08-08 10:00:00',
            ]);
            $run->videos()->attach($video->id, [
                'video_snapshot_id' => $videoSnapshot->id,
                'channel_snapshot_id' => $channelSnapshot->id,
                'result_rank' => $index,
                'page_number' => (int) ceil($index / 50),
                'provider_order' => (($index - 1) % 50) + 1,
            ]);
        }

        return $run->fresh();
    }

    /** @param  list<string>  $runIds */
    private function export(
        User $user,
        ExportStatus $status,
        array $runIds,
        ?string $path = null,
        ?string $expiresAt = null,
    ): ResearchExport {
        return ResearchExport::query()->create([
            'user_id' => $user->id,
            'format' => ExportFormat::Csv,
            'status' => $status,
            'selection' => [
                'type' => 'research_runs',
                'research_run_ids' => $runIds,
                'columns' => app(ResearchExportColumns::class)->all(),
            ],
            'disk' => $path === null ? null : 'local',
            'path' => $path,
            'size_bytes' => $path === null ? null : 14,
            'checksum_sha256' => $path === null ? null : hash('sha256', 'private export'),
            'completed_at' => $status === ExportStatus::Completed ? Date::now() : null,
            'expires_at' => $expiresAt,
            'failed_at' => $status === ExportStatus::Failed ? Date::now() : null,
            'error_code' => $status === ExportStatus::Failed ? 'export_generation_failed' : null,
            'error_message' => $status === ExportStatus::Failed ? 'The export could not be generated.' : null,
        ]);
    }

    private function temporaryPath(string $extension): string
    {
        return sys_get_temp_dir().DIRECTORY_SEPARATOR.'nishetube-export-'.str()->uuid().'.'.$extension;
    }
}
