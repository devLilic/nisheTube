<?php

namespace Tests\Feature\Exports;

use App\Domain\Exports\Enums\ExportFormat;
use App\Domain\Exports\Enums\ExportStatus;
use App\Domain\Exports\Services\ResearchExportColumns;
use App\Domain\Research\Enums\ResearchRunKind;
use App\Domain\Research\Enums\ResearchRunStatus;
use App\Jobs\Exports\GenerateResearchExport;
use App\Models\Market;
use App\Models\ResearchExport;
use App\Models\ResearchQuery;
use App\Models\ResearchRun;
use App\Models\User;
use Database\Seeders\MarketSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExportInterfaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MarketSeeder::class);
        Storage::fake('local');
        Queue::fake();
    }

    public function test_index_is_authenticated_deferred_and_owner_scoped_with_real_builder_options(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $run = $this->completedRun($owner, 'Owner export run');
        $this->completedRun($other, 'Foreign private run');
        $ownerExport = $this->export($owner, ExportStatus::Queued, [$run->public_id]);
        $this->export($other, ExportStatus::Completed, []);

        $this->get(route('exports.index'))->assertRedirect(route('login'));

        $this->actingAs($owner)->get(route('exports.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('exports/index')
                ->has('builder.runs', 1)
                ->where('builder.runs.0.public_id', $run->public_id)
                ->has('builder.column_groups.Research context')
                ->has('builder.default_columns', 34)
                ->missing('jobs')
                ->loadDeferredProps('default', fn (Assert $deferred): Assert => $deferred
                    ->has('jobs.items', 1)
                    ->where('jobs.items.0.public_id', $ownerExport->public_id)
                    ->where('jobs.has_active', true)))
            ->assertDontSee('Foreign private run');
    }

    public function test_store_validates_columns_and_queues_an_owner_selection(): void
    {
        $owner = User::factory()->create();
        $run = $this->completedRun($owner, 'Export selected columns');
        $columns = ['run_id', 'query', 'market', 'run_completed_at', 'video_title', 'views'];

        $this->actingAs($owner)->post(route('exports.store'), [
            'format' => 'csv',
            'research_run_ids' => [$run->public_id],
            'columns' => $columns,
        ])->assertRedirect(route('exports.index'));

        $export = ResearchExport::query()->firstOrFail();
        $this->assertSame($columns, $export->selection['columns']);
        Queue::assertPushed(GenerateResearchExport::class);

        $this->actingAs($owner)->from(route('exports.index'))->post(route('exports.store'), [
            'format' => 'xlsx',
            'research_run_ids' => [$run->public_id],
            'columns' => ['video_title'],
        ])->assertSessionHasErrors('columns');
    }

    public function test_download_retry_and_delete_enforce_state_expiry_and_ownership(): void
    {
        Date::setTestNow('2026-08-08 12:00:00');
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $path = 'exports/'.$owner->id.'/ready.csv';
        Storage::disk('local')->put($path, 'ready export');
        $ready = $this->export($owner, ExportStatus::Completed, [], $path, '2026-08-15 12:00:00');
        $failed = $this->export($owner, ExportStatus::Failed, []);
        $expired = $this->export($owner, ExportStatus::Completed, [], $path, '2026-08-07 12:00:00');

        $this->actingAs($other)->get(route('exports.download', $ready))->assertForbidden();
        $this->actingAs($owner)->get(route('exports.download', $ready))->assertOk();
        $this->actingAs($owner)->get(route('exports.download', $expired))->assertGone();

        $this->actingAs($owner)->post(route('exports.retry', $failed))->assertRedirect(route('exports.index'));
        $this->assertSame(ExportStatus::Queued, $failed->fresh()->status);
        Queue::assertPushed(GenerateResearchExport::class, fn (GenerateResearchExport $job): bool => $job->researchExportId === $failed->id);

        $this->actingAs($other)->delete(route('exports.destroy', $ready))->assertForbidden();
        $this->actingAs($owner)->delete(route('exports.destroy', $ready))->assertRedirect(route('exports.index'));
        $this->assertDatabaseMissing('exports', ['id' => $ready->id]);
        Storage::disk('local')->assertMissing($path);
    }

    private function completedRun(User $user, string $queryText): ResearchRun
    {
        $market = Market::query()->where('key', 'global_en')->firstOrFail();
        $query = ResearchQuery::query()->create(['user_id' => $user->id, 'market_id' => $market->id, 'query_text' => $queryText]);

        return ResearchRun::query()->create([
            'user_id' => $user->id, 'research_query_id' => $query->id, 'kind' => ResearchRunKind::Search,
            'status' => ResearchRunStatus::Completed, 'attempt_number' => 1, 'query_text' => $query->query_text,
            'market_key' => $market->key, 'region_code' => $market->region_code, 'relevance_language' => $market->relevance_language,
            'parameters' => ['search_order' => 'relevance'], 'requested_result_count' => 25,
            'collected_result_count' => 25, 'enriched_result_count' => 25, 'progress_percent' => 100,
            'completed_at' => '2026-08-08 10:00:00',
        ]);
    }

    /** @param list<string> $runIds */
    private function export(User $user, ExportStatus $status, array $runIds, ?string $path = null, ?string $expiresAt = null): ResearchExport
    {
        return ResearchExport::query()->create([
            'user_id' => $user->id, 'format' => ExportFormat::Csv, 'status' => $status,
            'selection' => ['type' => 'research_runs', 'research_run_ids' => $runIds, 'columns' => app(ResearchExportColumns::class)->all()],
            'disk' => $path === null ? null : 'local', 'path' => $path, 'size_bytes' => $path === null ? null : 12,
            'checksum_sha256' => $path === null ? null : hash('sha256', 'ready export'),
            'completed_at' => $status === ExportStatus::Completed ? '2026-08-08 11:00:00' : null,
            'expires_at' => $expiresAt, 'failed_at' => $status === ExportStatus::Failed ? '2026-08-08 11:00:00' : null,
            'error_code' => $status === ExportStatus::Failed ? 'export_generation_failed' : null,
            'error_message' => $status === ExportStatus::Failed ? 'The export could not be generated.' : null,
        ]);
    }
}
