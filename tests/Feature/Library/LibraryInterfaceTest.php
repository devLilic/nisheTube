<?php

namespace Tests\Feature\Library;

use App\Domain\Discovery\Actions\CreateDiscoveryRun;
use App\Domain\Library\Actions\AttachTag;
use App\Domain\Library\Actions\CreateFavorite;
use App\Domain\Library\Actions\CreateProject;
use App\Domain\Library\Actions\CreateTag;
use App\Domain\Research\Actions\CreateResearchQuery;
use App\Models\Market;
use App\Models\User;
use Database\Seeders\MarketSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
