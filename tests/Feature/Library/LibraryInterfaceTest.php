<?php

namespace Tests\Feature\Library;

use App\Domain\Discovery\Actions\CreateDiscoveryRun;
use App\Domain\Library\Actions\AttachTag;
use App\Domain\Library\Actions\CreateFavorite;
use App\Domain\Library\Actions\CreateProject;
use App\Domain\Library\Actions\CreateTag;
use App\Domain\Research\Actions\CreateResearchQuery;
use App\Domain\Research\Actions\CreateResearchRun;
use App\Models\Market;
use App\Models\User;
use Database\Seeders\MarketSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LibraryInterfaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MarketSeeder::class);
    }

    public function test_project_grid_and_list_are_owner_scoped_filterable_and_sortable(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        app(CreateProject::class)->handle($owner, 'Zulu project', 'Romanian channels', '#112233');
        $alpha = app(CreateProject::class)->handle($owner, 'Alpha project', 'English research', '#445566');
        app(CreateProject::class)->handle($other, 'Foreign project');

        $this->get(route('library.projects.index'))->assertRedirect(route('login'));

        $this->actingAs($owner)
            ->get(route('library.projects.index', ['search' => 'project', 'sort' => 'name', 'view' => 'list']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('library/projects/index')
                ->has('projects', 2)
                ->where('projects.0.public_id', $alpha->public_id)
                ->where('filters.view', 'list')
                ->where('filters.sort', 'name')
            )
            ->assertDontSee('Foreign project');
    }

    public function test_project_index_keeps_payload_and_query_count_bounded_as_projects_grow(): void
    {
        $owner = User::factory()->create();

        for ($index = 1; $index <= 30; $index++) {
            app(CreateProject::class)->handle($owner, "Performance project {$index}");
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($owner)
            ->get(route('library.projects.index', ['page' => 2]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('library/projects/index')
                ->has('projects', 6)
                ->where('pagination.current_page', 2)
                ->where('pagination.per_page', 24)
                ->where('pagination.total', 30));

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(10, $queryCount, 'Project-list queries must not grow with the number of rendered cards.');
    }

    public function test_project_detail_exposes_real_tabs_and_rejects_foreign_users(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $market = Market::query()->where('key', 'global_en')->firstOrFail();
        $project = app(CreateProject::class)->handle($owner, 'Compact living', 'Evidence notes');
        $query = app(CreateResearchQuery::class)->handle($owner, $market, 'small apartment storage', project: $project);
        $discovery = app(CreateDiscoveryRun::class)->handle($owner, $market, ['compact homes'], $project);
        app(CreateFavorite::class)->handle($owner, $query, $project, 'Strong evidence');

        $this->actingAs($other)->get(route('library.projects.show', $project))->assertForbidden();

        $this->actingAs($owner)
            ->get(route('library.projects.show', $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('library/projects/show')
                ->where('project.name', 'Compact living')
                ->has('project.queries', 1)
                ->where('project.queries.0.label', 'small apartment storage')
                ->has('project.discovery_runs', 1)
                ->where('project.discovery_runs.0.public_id', $discovery->public_id)
                ->has('project.favorites', 1)
            );
    }

    public function test_favorites_page_filters_real_notes_projects_and_tags_without_leakage(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $market = Market::query()->where('key', 'global_en')->firstOrFail();
        $project = app(CreateProject::class)->handle($owner, 'Priority ideas');
        $query = app(CreateResearchQuery::class)->handle($owner, $market, 'modular desks');
        $favorite = app(CreateFavorite::class)->handle($owner, $query, $project, 'Review this evidence');
        $tag = app(CreateTag::class)->handle($owner, 'Promising');
        app(AttachTag::class)->handle($owner, $tag, $query);
        $foreignQuery = app(CreateResearchQuery::class)->handle($other, $market, 'foreign private query');
        app(CreateFavorite::class)->handle($other, $foreignQuery, null, 'Review foreign evidence');

        $this->actingAs($owner)
            ->get(route('library.favorites.index', [
                'search' => 'review this',
                'project' => $project->public_id,
                'tag' => $tag->public_id,
                'type' => 'research_query',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('library/favorites/index')
                ->has('favorites', 1)
                ->where('favorites.0.public_id', $favorite->public_id)
                ->where('favorites.0.label', 'modular desks')
                ->where('favorites.0.note', 'Review this evidence')
                ->where('favorites.0.project.public_id', $project->public_id)
                ->where('favorites.0.tags.0.public_id', $tag->public_id)
                ->has('library.projects', 1)
                ->has('library.tags', 1)
            )
            ->assertDontSee('foreign private query');
    }

    public function test_owner_can_update_project_decision_context_and_read_derived_workspace_and_shortlist_context(): void
    {
        $owner = User::factory()->create();
        $market = Market::query()->where('key', 'global_en')->firstOrFail();
        $project = app(CreateProject::class)->handle($owner, 'Decision project');
        $query = app(CreateResearchQuery::class)->handle($owner, $market, 'decision shortlist query', project: $project);
        $run = app(CreateResearchRun::class)->handle($owner, $query, 10);
        app(CreateFavorite::class)->handle($owner, $run, $project);
        $workspace = $owner->topicWorkspaces()->create([
            'market_id' => $market->id,
            'name' => 'Decision workspace',
            'name_key' => 'decision workspace',
            'market_key' => $market->key,
            'region_code' => $market->region_code,
            'relevance_language' => $market->relevance_language,
            'research_project_id' => $project->id,
        ]);

        $this->actingAs($owner)->patch(route('library.projects.update', $project), [
            'name' => 'Decision project',
            'description' => '',
            'color' => '',
            'purpose' => 'Choose a sustainable topic direction.',
            'market_key' => $market->key,
            'themes' => 'Small homes, Storage, small homes',
            'decision_status' => 'active',
            'decision_note' => 'Compare stored evidence before committing.',
        ])->assertRedirect();

        $this->actingAs($owner)->get(route('library.projects.show', $project))->assertInertia(fn (Assert $page): Assert => $page
            ->where('project.purpose', 'Choose a sustainable topic direction.')
            ->where('project.market_key', $market->key)
            ->where('project.themes', ['Small homes', 'Storage'])
            ->where('project.decision_status', 'active')
            ->where('project.workspaces.0.public_id', $workspace->public_id)
            ->where('project.shortlist.0.label', 'decision shortlist query')
            ->has('markets', 3));
    }
}
