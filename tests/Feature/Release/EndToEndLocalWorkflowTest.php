<?php

namespace Tests\Feature\Release;

use App\Domain\Exports\Enums\ExportStatus;
use App\Domain\Library\Enums\LibraryTargetType;
use App\Domain\Research\Enums\ResearchRunStatus;
use App\Domain\Retention\Enums\CleanupStatus;
use App\Domain\Retention\Enums\DeletionOutcome;
use App\Jobs\Exports\GenerateResearchExport;
use App\Jobs\Research\CollectResearchRunSearch;
use App\Jobs\Research\EnrichResearchRun;
use App\Jobs\Research\ScoreResearchRun;
use App\Jobs\Retention\ExecuteCleanupRun;
use App\Models\CleanupRun;
use App\Models\ResearchExport;
use App\Models\ResearchProject;
use App\Models\ResearchRun;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\MarketSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class EndToEndLocalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MarketSeeder::class);
        config()->set('youtube.api_key', 'release-smoke-test-secret');
        config()->set('youtube.max_attempts', 1);
        config()->set('youtube.retry_delay_milliseconds', 0);
        CarbonImmutable::setTestNow('2026-08-01 12:00:00 UTC');
        Queue::fake();
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_registered_user_can_complete_the_local_research_lifecycle_through_cleanup(): void
    {
        $this->fakeYouTubeWorkflow();

        $this->post(route('register.store'), [
            'name' => 'Release Researcher',
            'email' => 'release@example.test',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $user = User::query()->where('email', 'release@example.test')->sole();
        $this->assertAuthenticatedAs($user);

        $this->put(route('preferences.update'), [
            'timezone' => 'Europe/Bucharest',
            'default_market_key' => 'ro_ro',
            'default_result_depth' => 25,
        ])->assertSessionHasNoErrors();

        $this->assertSame('Europe/Bucharest', $user->fresh()->timezone);
        $this->assertSame('ro_ro', $user->fresh()->default_market_key);

        $firstRun = $this->submitAndCompleteResearch('mobilier pentru apartamente mici');

        $this->get(route('research.runs.show', $firstRun))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('run.status', ResearchRunStatus::Completed->value)
                ->where('run.analysis.summary.video_count', 1)
                ->where('run.analysis.summary.channel_count', 1)
                ->where('run.score.formula_version', 'niche-opportunity-v1')
                ->where('run.score.sample_size', 1));

        $this->post(route('library.projects.store'), [
            'name' => 'Small-space ideas',
            'description' => 'Release smoke workflow',
            'color' => '#2563EB',
        ])->assertRedirect(route('library.projects.index'));

        $project = ResearchProject::query()->where('user_id', $user->id)->sole();
        $this->from(route('research.runs.show', $firstRun))
            ->post(route('library.favorites.store'), [
                'target_type' => LibraryTargetType::ResearchRun->value,
                'target_reference' => $firstRun->public_id,
                'project_public_id' => $project->public_id,
                'note' => 'Keep the initial benchmark.',
            ])->assertRedirect(route('research.runs.show', $firstRun));

        CarbonImmutable::setTestNow('2026-08-08 12:00:00 UTC');
        $secondRun = $this->submitAndCompleteResearch('  Mobilier pentru apartamente mici  ');

        $this->get(route('history.compare', [$firstRun, $secondRun]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('pair.before.public_id', $firstRun->public_id)
                ->where('pair.after.public_id', $secondRun->public_id)
                ->loadDeferredProps('default', fn (Assert $deferred): Assert => $deferred
                    ->where('comparison.compatibility.comparable', true)
                    ->where('comparison.compatibility.score_comparable', true)
                    ->has('comparison.score_deltas.overall_score.delta')));

        $columns = ['run_id', 'query', 'market', 'run_completed_at', 'video_title', 'views'];

        foreach (['csv', 'xlsx'] as $format) {
            $this->post(route('exports.store'), [
                'format' => $format,
                'research_run_ids' => [$firstRun->public_id, $secondRun->public_id],
                'columns' => $columns,
            ])->assertRedirect(route('exports.index'));

            $export = ResearchExport::query()->where('format', $format)->sole();
            app()->call([new GenerateResearchExport($export->id), 'handle']);

            $this->assertSame(ExportStatus::Completed, $export->fresh()->status);
            Storage::disk('local')->assertExists($export->fresh()->path);
            $this->get(route('exports.download', $export))->assertOk();
        }

        $this->post(route('retention.preview'))->assertRedirect(route('retention.index'));

        $this->delete(route('retention.runs.destroy'), [
            'research_run_ids' => [$firstRun->public_id],
            'confirmation' => true,
            'favorite_impact_confirmed' => true,
        ])->assertRedirect(route('retention.index'));

        $cleanup = CleanupRun::query()->where('dry_run', false)->sole();
        app()->call([new ExecuteCleanupRun($cleanup->id), 'handle']);

        $this->assertSame(CleanupStatus::Completed, $cleanup->fresh()->status);
        $this->assertDatabaseMissing('research_runs', ['id' => $firstRun->id]);
        $this->assertDatabaseMissing('favorites', [
            'target_type' => LibraryTargetType::ResearchRun->value,
            'target_id' => $firstRun->id,
        ]);
        $this->assertDatabaseHas('research_runs', ['id' => $secondRun->id]);
        $this->assertDatabaseHas('research_projects', ['id' => $project->id]);
        $this->assertDatabaseHas('snapshot_deletion_items', [
            'cleanup_run_id' => $cleanup->id,
            'target_reference' => $firstRun->public_id,
            'outcome' => DeletionOutcome::Deleted->value,
            'favorite_impacted' => true,
        ]);
    }

    private function submitAndCompleteResearch(string $query): ResearchRun
    {
        $this->post(route('research.store'), [
            'query_text' => $query,
            'market_key' => 'ro_ro',
            'requested_result_count' => 25,
            'search_order' => 'relevance',
            'published_window' => 'any',
            'video_duration' => 'any',
        ])->assertSessionHasNoErrors();

        $run = ResearchRun::query()->latest('id')->firstOrFail();

        app()->call([new CollectResearchRunSearch($run->id), 'handle']);
        app()->call([new EnrichResearchRun($run->id), 'handle']);
        app()->call([new ScoreResearchRun($run->id), 'handle']);

        $run->refresh();
        $this->assertSame(ResearchRunStatus::Completed, $run->status);
        $this->assertSame(1, $run->collected_result_count);
        $this->assertSame(1, $run->enriched_result_count);
        $this->assertNotNull($run->opportunityScores()->first());

        return $run;
    }

    private function fakeYouTubeWorkflow(): void
    {
        $videoRequest = 0;

        Http::fake(function (Request $request) use (&$videoRequest) {
            if (str_contains($request->url(), '/search')) {
                return Http::response(['items' => [[
                    'id' => ['videoId' => 'workflow-video'],
                    'snippet' => [
                        'channelId' => 'workflow-channel',
                        'title' => 'Mobilier compact pentru spații mici',
                        'publishedAt' => '2026-07-01T12:00:00Z',
                    ],
                ]]]);
            }

            if (str_contains($request->url(), '/videos')) {
                $videoRequest++;

                return Http::response(['items' => [[
                    'id' => 'workflow-video',
                    'snippet' => [
                        'channelId' => 'workflow-channel',
                        'channelTitle' => 'Atelier Compact',
                        'title' => 'Mobilier compact pentru spații mici',
                        'publishedAt' => '2026-07-01T12:00:00Z',
                        'categoryId' => '26',
                        'thumbnails' => ['high' => ['url' => 'https://example.test/workflow-video.jpg']],
                    ],
                    'contentDetails' => ['duration' => 'PT8M30S'],
                    'statistics' => [
                        'viewCount' => (string) (10000 * $videoRequest),
                        'likeCount' => (string) (500 * $videoRequest),
                        'commentCount' => (string) (50 * $videoRequest),
                    ],
                ]]]);
            }

            return Http::response(['items' => [[
                'id' => 'workflow-channel',
                'snippet' => [
                    'title' => 'Atelier Compact',
                    'customUrl' => '@ateliercompact',
                    'country' => 'RO',
                    'publishedAt' => '2020-01-01T00:00:00Z',
                ],
                'contentDetails' => ['relatedPlaylists' => ['uploads' => 'workflow-uploads']],
                'statistics' => [
                    'subscriberCount' => '2500',
                    'viewCount' => '250000',
                    'videoCount' => '40',
                    'hiddenSubscriberCount' => false,
                ],
            ]]]);
        });
    }
}
