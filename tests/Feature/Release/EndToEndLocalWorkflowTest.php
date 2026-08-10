<?php

namespace Tests\Feature\Release;

use App\Domain\Analyzer\Enums\AnalyzerRunStatus;
use App\Domain\Discovery\Enums\DiscoveryRunStatus;
use App\Domain\Exports\Enums\ExportStatus;
use App\Domain\Library\Enums\LibraryTargetType;
use App\Domain\Research\Enums\ResearchRunStatus;
use App\Domain\Retention\Enums\CleanupStatus;
use App\Domain\Retention\Enums\DeletionOutcome;
use App\Jobs\Analyzer\CollectAnalyzerRun;
use App\Jobs\Discovery\GenerateDiscoveryCandidates;
use App\Jobs\Exports\GenerateResearchExport;
use App\Jobs\Research\CollectResearchRunSearch;
use App\Jobs\Research\EnrichResearchRun;
use App\Jobs\Research\ScoreResearchRun;
use App\Jobs\Retention\ExecuteCleanupRun;
use App\Models\AnalyzerRun;
use App\Models\ApiUsageEvent;
use App\Models\CleanupRun;
use App\Models\DiscoveryRun;
use App\Models\ResearchExport;
use App\Models\ResearchProject;
use App\Models\ResearchRun;
use App\Models\TopicWorkspace;
use App\Models\User;
use App\Models\WatchlistItem;
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

    public function test_integrated_expansion_keeps_canonical_evidence_quota_and_ownership_boundaries_intact(): void
    {
        $this->fakeYouTubeWorkflow();

        $owner = User::factory()->create([
            'timezone' => 'Europe/Bucharest',
            'default_market_key' => 'ro_ro',
            'default_result_depth' => 25,
        ]);
        $other = User::factory()->create();
        $this->actingAs($owner);

        $firstRun = $this->submitAndCompleteResearch('organizare pentru apartamente mici');
        $video = $firstRun->videos()->sole();

        $this->from(route('research.runs.show', $firstRun))->post(route('analyzer.store'), [
            'video_reference' => $video->provider_video_id,
            'origin_kind' => 'search',
            'origin_reference' => $firstRun->public_id,
            'return_to' => "/research/runs/{$firstRun->public_id}?tab=videos",
        ])->assertRedirect();

        $analyzerRun = AnalyzerRun::query()->latest('id')->firstOrFail();
        app()->call([new CollectAnalyzerRun($analyzerRun->id), 'handle']);
        $analyzerRun->refresh();

        $this->assertSame(AnalyzerRunStatus::Completed, $analyzerRun->status);
        $this->assertSame($video->id, $analyzerRun->video_id);
        $this->assertSame($firstRun->public_id, $analyzerRun->origin_reference);
        $this->assertGreaterThanOrEqual(3, $analyzerRun->videoMemberships()->count());

        $quotaBeforeReadOnlySurfaces = ApiUsageEvent::query()->count();
        $this->get(route('analyzer.runs.show', $analyzerRun))->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('run.video.title', 'Mobilier compact pentru spații mici — хранение для квартиры')
                ->where('run.origin.reference', $firstRun->public_id));
        $this->get(route('explore.index', ['source' => 'analyzer']))->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('capabilities.provider_io_during_browsing', false)
                ->where('results.data.0.id', $video->provider_video_id));
        $this->assertSame($quotaBeforeReadOnlySurfaces, ApiUsageEvent::query()->count());

        $this->from(route('analyzer.runs.show', $analyzerRun))->post(route('watchlist.store'), [
            'target_type' => 'video',
            'target_reference' => $video->provider_video_id,
            'note' => 'Urmărește evoluția — отслеживать динамику.',
        ])->assertRedirect();
        $watchlistItem = WatchlistItem::query()->where('user_id', $owner->id)->sole();

        $this->post(route('topics.store'), [
            'name' => 'Spații mici — хранение и организация',
            'description' => 'Evidence across Romanian and Russian titles.',
            'market_key' => 'ro_ro',
        ])->assertRedirect();
        $workspace = TopicWorkspace::query()->where('user_id', $owner->id)->sole();

        $this->patch(route('watchlist.update', $watchlistItem), [
            'status' => 'monitoring',
            'is_active' => true,
            'workspace' => $workspace->public_id,
            'note' => 'Urmărește evoluția — отслеживать динамику.',
        ])->assertRedirect();
        $this->post(route('topics.evidence.store', $workspace), [
            'target_type' => 'analyzer_run',
            'target_reference' => $analyzerRun->public_id,
            'evidence_role' => 'example',
            'note' => 'Canonical Analyzer evidence.',
        ])->assertRedirect();
        $this->post(route('topics.evidence.store', $workspace), [
            'target_type' => 'research_run',
            'target_reference' => $firstRun->public_id,
            'evidence_role' => 'evidence',
        ])->assertRedirect();

        $this->post(route('topics.launch.discovery', $workspace), [
            'confirmed' => true,
            'research_run' => $firstRun->public_id,
        ])->assertRedirect();
        $discoveryRun = DiscoveryRun::query()->where('user_id', $owner->id)->sole();
        app()->call([new GenerateDiscoveryCandidates($discoveryRun->id), 'handle']);
        $this->assertSame(DiscoveryRunStatus::Completed, $discoveryRun->fresh()->status);
        $candidate = $discoveryRun->candidates()->firstOrFail();

        $this->post(route('discovery.candidates.validate', $candidate), [
            'requested_result_count' => 25,
        ])->assertRedirect();
        $validationRun = $candidate->fresh()->validationResearchRun;
        $this->assertNotNull($validationRun);
        $this->assertSame('discovery_validation', $validationRun->kind->value);

        CarbonImmutable::setTestNow('2026-08-08 12:00:00 UTC');
        $secondRun = $this->submitAndCompleteResearch(' Organizare pentru apartamente mici ');
        $this->get(route('history.compare', [$firstRun, $secondRun]))->assertOk();

        $this->post(route('exports.store'), [
            'format' => 'csv',
            'research_run_ids' => [$firstRun->public_id, $secondRun->public_id],
            'columns' => ['run_id', 'query', 'market', 'run_completed_at', 'video_title', 'views'],
        ])->assertRedirect(route('exports.index'));
        $export = ResearchExport::query()->sole();
        app()->call([new GenerateResearchExport($export->id), 'handle']);
        $this->get(route('exports.download', $export->fresh()))->assertOk();

        CarbonImmutable::setTestNow('2027-03-01 12:00:00 UTC');
        $this->post(route('retention.preview'))->assertRedirect(route('retention.index'));
        $preview = CleanupRun::query()->where('dry_run', true)->latest('id')->firstOrFail();
        $this->assertGreaterThan(0, array_sum($preview->eligible_counts));

        $this->actingAs($other)->get(route('analyzer.runs.show', $analyzerRun))->assertForbidden();
        $this->actingAs($other)->get(route('topics.show', $workspace))->assertForbidden();
        $this->actingAs($other)->patch(route('watchlist.update', $watchlistItem), [
            'status' => 'monitoring',
            'is_active' => true,
        ])->assertForbidden();
        $this->actingAs($other)->get(route('exports.download', $export))->assertForbidden();
        $this->actingAs($other)->get(route('explore.index', ['search' => 'хранение']))->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page->has('results.data', 0));
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
                    'id' => ['videoId' => 'workflow01A'],
                    'snippet' => [
                        'channelId' => 'workflow-channel',
                        'title' => 'Mobilier compact pentru spații mici — хранение для квартиры',
                        'publishedAt' => '2026-07-01T12:00:00Z',
                    ],
                ]]]);
            }

            if (str_contains($request->url(), '/playlistItems')) {
                return Http::response(['items' => [
                    ['contentDetails' => ['videoId' => 'cohort00001']],
                    ['contentDetails' => ['videoId' => 'cohort00002']],
                    ['contentDetails' => ['videoId' => 'cohort00003']],
                ]]);
            }

            if (str_contains($request->url(), '/videoCategories')) {
                return Http::response(['items' => [[
                    'id' => '26',
                    'snippet' => ['title' => 'Howto & Style', 'assignable' => true],
                ]]]);
            }

            if (str_contains($request->url(), '/videos')) {
                $videoRequest++;

                $ids = explode(',', (string) $request->data()['id']);
                $items = array_map(fn (string $id, int $index): array => [
                    'id' => $id,
                    'snippet' => [
                        'channelId' => 'workflow-channel',
                        'channelTitle' => 'Atelier Compact',
                        'title' => $id === 'workflow01A'
                            ? 'Mobilier compact pentru spații mici — хранение для квартиры'
                            : "Idei compacte {$index} — организация дома",
                        'publishedAt' => sprintf('2026-07-%02dT12:00:00Z', min(28, $index + 1)),
                        'categoryId' => '26',
                        'thumbnails' => ['high' => ['url' => "https://i.ytimg.com/vi/{$id}/hqdefault.jpg"]],
                    ],
                    'contentDetails' => ['duration' => 'PT8M30S'],
                    'statistics' => [
                        'viewCount' => (string) ((10000 * $videoRequest) + ($index * 1000)),
                        'likeCount' => (string) ((500 * $videoRequest) + ($index * 50)),
                        'commentCount' => (string) ((50 * $videoRequest) + $index),
                    ],
                ], $ids, array_keys($ids));

                return Http::response(['items' => $items]);
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
