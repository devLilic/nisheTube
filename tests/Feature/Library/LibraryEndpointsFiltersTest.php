<?php

namespace Tests\Feature\Library;

use App\Domain\Library\Actions\AttachTag;
use App\Domain\Library\Actions\CreateFavorite;
use App\Domain\Library\Actions\CreateProject;
use App\Domain\Library\Actions\CreateTag;
use App\Domain\Library\Actions\SetProjectArchived;
use App\Domain\Library\Data\LibraryFilters;
use App\Domain\Library\Enums\LibraryTargetType;
use App\Domain\Library\ReadModels\LibraryQueries;
use App\Domain\Research\Actions\CreateResearchQuery;
use App\Models\Market;
use App\Models\User;
use Database\Seeders\MarketSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LibraryEndpointsFiltersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MarketSeeder::class);
    }

    public function test_project_endpoints_require_authentication_validate_input_and_enforce_ownership(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $foreignProject = app(CreateProject::class)->handle($otherUser, 'Foreign project');

        $this->post(route('library.projects.store'), ['name' => 'Guest project'])
            ->assertRedirect(route('login'));

        $this->actingAs($owner)
            ->post(route('library.projects.store'), [
                'name' => '  New   project ',
                'description' => '  Research notes. ',
                'color' => '#abcdef',
            ])
            ->assertRedirect();
        $this->assertDatabaseHas('research_projects', [
            'user_id' => $owner->id,
            'name' => 'New project',
            'description' => 'Research notes.',
            'color' => '#ABCDEF',
        ]);

        $this->actingAs($owner)
            ->post(route('library.projects.store'), ['name' => '', 'color' => 'red'])
            ->assertSessionHasErrors(['name', 'color']);

        $this->actingAs($owner)
            ->patch(route('library.projects.update', $foreignProject), ['name' => 'Intrusion'])
            ->assertForbidden();
        $this->actingAs($owner)
            ->post(route('library.projects.archive', $foreignProject))
            ->assertForbidden();
        $this->actingAs($owner)
            ->delete(route('library.projects.destroy', $foreignProject))
            ->assertForbidden();
    }

    public function test_favorite_and_tag_endpoints_enforce_allow_list_resolution_and_owner_boundaries(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $query = app(CreateResearchQuery::class)->handle($owner, $this->market(), 'saved query');

        $this->actingAs($owner)
            ->post(route('library.favorites.store'), [
                'target_type' => LibraryTargetType::ResearchQuery->value,
                'target_reference' => $query->public_id,
                'note' => '  Useful evidence. ',
            ])
            ->assertRedirect();
        $favorite = $owner->favorites()->firstOrFail();
        $this->assertSame('Useful evidence.', $favorite->note);

        $this->actingAs($owner)
            ->post(route('library.favorites.store'), [
                'target_type' => 'market',
                'target_reference' => (string) $this->market()->id,
            ])
            ->assertSessionHasErrors('target_type');
        $this->actingAs($otherUser)
            ->post(route('library.favorites.store'), [
                'target_type' => LibraryTargetType::ResearchQuery->value,
                'target_reference' => $query->public_id,
            ])
            ->assertNotFound();
        $this->actingAs($otherUser)
            ->patch(route('library.favorites.update', $favorite), ['note' => 'Foreign edit'])
            ->assertForbidden();

        $this->actingAs($owner)
            ->post(route('library.tags.store'), ['name' => '  Priority ', 'color' => '#123456'])
            ->assertRedirect();
        $tag = $owner->tags()->firstOrFail();
        $this->actingAs($owner)
            ->post(route('library.tags.attach', $tag), [
                'target_type' => LibraryTargetType::ResearchQuery->value,
                'target_reference' => $query->public_id,
            ])
            ->assertRedirect();
        $this->assertDatabaseHas('taggables', ['tag_id' => $tag->id, 'target_id' => $query->id]);

        $this->actingAs($otherUser)
            ->delete(route('library.tags.destroy', $tag))
            ->assertForbidden();
    }

    public function test_library_filters_are_useful_and_never_leak_another_users_records(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $activeProject = app(CreateProject::class)->handle($owner, 'Active ideas', 'Small homes');
        $archivedProject = app(CreateProject::class)->handle($owner, 'Archived ideas');
        app(SetProjectArchived::class)->handle($owner, $archivedProject, true);
        app(CreateProject::class)->handle($otherUser, 'Active foreign project');

        $matchingQuery = app(CreateResearchQuery::class)->handle($owner, $this->market(), 'matching query');
        $otherQuery = app(CreateResearchQuery::class)->handle($owner, $this->market(), 'other query');
        $foreignQuery = app(CreateResearchQuery::class)->handle($otherUser, $this->market(), 'foreign query');
        $matchingFavorite = app(CreateFavorite::class)->handle($owner, $matchingQuery, $activeProject, 'Strong compact-home evidence');
        app(CreateFavorite::class)->handle($owner, $otherQuery, null, 'Unrelated note');
        app(CreateFavorite::class)->handle($otherUser, $foreignQuery, null, 'Strong foreign evidence');
        $tag = app(CreateTag::class)->handle($owner, 'Promising');
        app(CreateTag::class)->handle($otherUser, 'Promising foreign');
        app(AttachTag::class)->handle($owner, $tag, $matchingQuery);

        $queries = app(LibraryQueries::class);

        $this->assertEquals(
            [$activeProject->id],
            $queries->projects($owner, new LibraryFilters(search: 'small homes', archived: false))->pluck('id')->all(),
        );
        $this->assertEquals(
            [$archivedProject->id],
            $queries->projects($owner, new LibraryFilters(archived: true))->pluck('id')->all(),
        );
        $this->assertEquals(
            [$matchingFavorite->id],
            $queries->favorites($owner, new LibraryFilters(
                targetType: LibraryTargetType::ResearchQuery,
                projectId: $activeProject->id,
                tagId: $tag->id,
                search: 'compact-home',
            ))->pluck('id')->all(),
        );
        $this->assertEquals(
            [$tag->id],
            $queries->tags($owner, new LibraryFilters(search: 'promis'))->pluck('id')->all(),
        );
    }

    private function market(): Market
    {
        return Market::query()->where('key', 'global_en')->firstOrFail();
    }
}
