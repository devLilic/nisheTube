<?php

namespace Tests\Feature\Release;

use App\Domain\Library\Actions\CreateFavorite;
use App\Domain\Research\Actions\CreateResearchQuery;
use App\Domain\YouTube\Services\YouTubeIdBatcher;
use App\Http\ViewModels\LibraryViewModel;
use App\Models\Market;
use App\Models\User;
use Database\Seeders\MarketSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class PerformanceDatabaseReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MarketSeeder::class);
    }

    public function test_hot_owner_status_and_order_paths_have_composite_indexes(): void
    {
        $this->assertContains('research_runs_owner_status_completed_index', $this->indexNames('research_runs'));
        $this->assertContains('research_runs_owner_status_failed_index', $this->indexNames('research_runs'));
        $this->assertContains('favorites_owner_updated_index', $this->indexNames('favorites'));
        $this->assertContains('favorites_owner_type_updated_index', $this->indexNames('favorites'));
        $this->assertContains('opportunity_scores_formula_calculated_score_index', $this->indexNames('opportunity_scores'));
        $this->assertContains('exports_owner_created_index', $this->indexNames('exports'));
    }

    public function test_integrated_research_surfaces_keep_owner_scoped_lookup_indexes(): void
    {
        $this->assertContains('collection_runs_owner_created_index', $this->indexNames('collection_runs'));
        $this->assertContains('analyzer_runs_owner_created_index', $this->indexNames('analyzer_runs'));
        $this->assertContains('analyzer_runs_owner_video_completed_index', $this->indexNames('analyzer_runs'));
        $this->assertContains('analyzer_runs_owner_channel_completed_index', $this->indexNames('analyzer_runs'));
        $this->assertContains('watchlist_owner_state_index', $this->indexNames('watchlist_items'));
        $this->assertContains('watchlist_refresh_owner_state_index', $this->indexNames('watchlist_refresh_runs'));
        $this->assertContains('topic_workspace_owner_state_index', $this->indexNames('topic_workspaces'));
        $this->assertContains('semantic_profiles_owner_calculated_index', $this->indexNames('semantic_topic_profiles'));
        $this->assertContains('comment_runs_owner_completed_index', $this->indexNames('comment_collection_runs'));
        $this->assertContains('transcripts_owner_provided_index', $this->indexNames('transcript_documents'));
        $this->assertContains('thumb_profiles_owner_run_created_index', $this->indexNames('thumbnail_analysis_profiles'));
    }

    public function test_large_library_results_are_server_paginated_without_n_plus_one_growth(): void
    {
        $owner = User::factory()->create();
        $market = Market::query()->where('key', 'global_en')->firstOrFail();

        for ($index = 1; $index <= 30; $index++) {
            $query = app(CreateResearchQuery::class)->handle($owner, $market, "performance query {$index}");
            app(CreateFavorite::class)->handle($owner, $query, note: "Evidence {$index}");
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $result = app(LibraryViewModel::class)->favoritesIndex(
            $owner,
            Request::create('/favorites', 'GET'),
        );
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertCount(24, $result['favorites']);
        $this->assertSame(30, $result['pagination']['total']);
        $this->assertSame(2, $result['pagination']['last_page']);
        $this->assertSame([], $result['library']['favorites']);
        $this->assertLessThanOrEqual(10, $queryCount);

        $this->actingAs($owner)
            ->get(route('library.favorites.index', ['page' => 2]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->has('favorites', 6)
                ->where('pagination.current_page', 2)
                ->where('pagination.total', 30));
    }

    public function test_project_lists_are_paginated_and_external_id_batches_stay_within_provider_limits(): void
    {
        $owner = User::factory()->create();

        for ($index = 1; $index <= 30; $index++) {
            $owner->researchProjects()->create(['name' => "Project {$index}"]);
        }

        $this->actingAs($owner)
            ->get(route('library.projects.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->has('projects', 24)
                ->where('pagination.current_page', 1)
                ->where('pagination.last_page', 2)
                ->where('pagination.total', 30));

        $batches = app(YouTubeIdBatcher::class)->batches(
            array_map(fn (int $id): string => "video-{$id}", range(1, 200)),
        );

        $this->assertCount(4, $batches);
        $this->assertSame([50, 50, 50, 50], array_map('count', $batches));
        $this->assertSame(100, config('exports.max_research_runs'));
    }

    /** @return list<string> */
    private function indexNames(string $table): array
    {
        return array_values(array_map(
            fn (array $index): string => (string) $index['name'],
            Schema::getIndexes($table),
        ));
    }
}
