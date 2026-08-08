<?php

namespace Tests\Feature\Library;

use App\Domain\Discovery\Actions\CreateDiscoveryRun;
use App\Domain\Discovery\Enums\NicheCandidateStatus;
use App\Domain\Library\Actions\AttachTag;
use App\Domain\Library\Actions\CreateFavorite;
use App\Domain\Library\Actions\CreateProject;
use App\Domain\Library\Actions\CreateTag;
use App\Domain\Library\Actions\SetProjectArchived;
use App\Domain\Library\Data\LibraryFilters;
use App\Domain\Library\Enums\LibraryTargetType;
use App\Domain\Library\ReadModels\LibraryQueries;
use App\Domain\Library\Services\ResolveLibraryTarget;
use App\Domain\Research\Actions\CreateResearchQuery;
use App\Domain\Research\Actions\CreateResearchRun;
use App\Models\Channel;
use App\Models\Market;
use App\Models\User;
use App\Models\Video;
use Database\Seeders\MarketSeeder;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LibraryAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MarketSeeder::class);
    }

    public function test_every_allowed_target_is_resolved_only_for_a_user_who_observed_or_owns_it(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $targets = $this->ownedTargets($owner);
        $resolver = app(ResolveLibraryTarget::class);

        foreach ($targets as $typeValue => [$target, $reference]) {
            $type = LibraryTargetType::from($typeValue);
            $resolved = $resolver->handle($owner, $type, $reference);
            $this->assertTrue($resolved->is($target));

            try {
                $resolver->handle($otherUser, $type, $reference);
                $this->fail("A foreign {$type->value} target was resolved.");
            } catch (ModelNotFoundException) {
                $this->addToAssertionCount(1);
            }
        }

        try {
            $resolver->handle(
                $owner,
                LibraryTargetType::ResearchRun,
                $targets[LibraryTargetType::ResearchQuery->value][1],
            );
            $this->fail('A reference for a different allowed target type was resolved.');
        } catch (ModelNotFoundException) {
            $this->addToAssertionCount(1);
        }

        $this->actingAs($owner)
            ->post(route('library.favorites.store'), [
                'target_type' => 'market',
                'target_reference' => (string) $this->market()->id,
            ])
            ->assertSessionHasErrors('target_type');
        $this->actingAs($otherUser)
            ->post(route('library.favorites.store'), [
                'target_type' => LibraryTargetType::ResearchQuery->value,
                'target_reference' => $targets[LibraryTargetType::ResearchQuery->value][1],
            ])
            ->assertNotFound();
        $this->assertDatabaseCount('favorites', 0);
    }

    public function test_favorite_tag_and_attachment_duplicates_are_rejected_or_idempotent(): void
    {
        $user = User::factory()->create();
        [$query] = $this->ownedTargets($user)[LibraryTargetType::ResearchQuery->value];
        $favorite = app(CreateFavorite::class)->handle($user, $query, note: 'Original note');

        try {
            app(CreateFavorite::class)->handle($user, $query, note: 'Duplicate note');
            $this->fail('A duplicate favorite was created.');
        } catch (DomainException $exception) {
            $this->assertSame('This item is already saved to favorites.', $exception->getMessage());
        }

        $tag = app(CreateTag::class)->handle($user, 'High Intent');

        try {
            app(CreateTag::class)->handle($user, '  high   intent  ');
            $this->fail('A normalized duplicate tag was created.');
        } catch (DomainException $exception) {
            $this->assertSame('A tag with this name already exists.', $exception->getMessage());
        }

        $firstAttachment = app(AttachTag::class)->handle($user, $tag, $query);
        $secondAttachment = app(AttachTag::class)->handle($user, $tag, $query);

        $this->assertSame($firstAttachment->id, $secondAttachment->id);
        $this->assertDatabaseCount('favorites', 1);
        $this->assertDatabaseHas('favorites', [
            'id' => $favorite->id,
            'note' => 'Original note',
        ]);
        $this->assertDatabaseCount('tags', 1);
        $this->assertDatabaseCount('taggables', 1);
    }

    public function test_archived_projects_remain_visible_only_when_requested_and_reject_new_assignments(): void
    {
        $user = User::factory()->create();
        $activeProject = app(CreateProject::class)->handle($user, 'Active project');
        $archivedProject = app(CreateProject::class)->handle($user, 'Archived project');
        app(SetProjectArchived::class)->handle($user, $archivedProject, true);
        $targets = $this->ownedTargets($user);
        [$savedQuery] = $targets[LibraryTargetType::ResearchQuery->value];
        [, $runReference] = $targets[LibraryTargetType::ResearchRun->value];
        $favorite = app(CreateFavorite::class)->handle($user, $savedQuery, $activeProject, 'Keep assignment');

        $queries = app(LibraryQueries::class);
        $this->assertEquals(
            [$activeProject->id],
            $queries->projects($user, new LibraryFilters(archived: false))->pluck('id')->all(),
        );
        $this->assertEquals(
            [$archivedProject->id],
            $queries->projects($user, new LibraryFilters(archived: true))->pluck('id')->all(),
        );
        $this->assertEqualsCanonicalizing(
            [$activeProject->id, $archivedProject->id],
            $queries->projects($user, new LibraryFilters)->pluck('id')->all(),
        );

        $this->actingAs($user)
            ->post(route('library.favorites.store'), [
                'target_type' => LibraryTargetType::ResearchRun->value,
                'target_reference' => $runReference,
                'project_public_id' => $archivedProject->public_id,
            ])
            ->assertForbidden();
        $this->actingAs($user)
            ->patch(route('library.favorites.update', $favorite), [
                'project_public_id' => $archivedProject->public_id,
                'note' => 'Changed through an archived project',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('favorites', [
            'user_id' => $user->id,
            'target_type' => LibraryTargetType::ResearchRun->value,
        ]);
        $this->assertDatabaseHas('favorites', [
            'id' => $favorite->id,
            'research_project_id' => $activeProject->id,
            'note' => 'Keep assignment',
        ]);

        $this->actingAs($user)
            ->get(route('library.favorites.index'))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->has('library.projects', 1)
                ->where('library.projects.0.public_id', $activeProject->public_id)
            );
    }

    public function test_each_library_filter_is_owner_scoped_and_foreign_filter_ids_disclose_nothing(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $project = app(CreateProject::class)->handle($owner, 'Owner project');
        $foreignProject = app(CreateProject::class)->handle($otherUser, 'Foreign secret project');
        $targets = $this->ownedTargets($owner);
        [$query] = $targets[LibraryTargetType::ResearchQuery->value];
        [$run] = $targets[LibraryTargetType::ResearchRun->value];
        $queryFavorite = app(CreateFavorite::class)->handle($owner, $query, $project, 'Alpha evidence');
        $runFavorite = app(CreateFavorite::class)->handle($owner, $run, null, 'Beta evidence');
        $tag = app(CreateTag::class)->handle($owner, 'Owner tag');
        $foreignTag = app(CreateTag::class)->handle($otherUser, 'Foreign secret tag');
        app(AttachTag::class)->handle($owner, $tag, $query);
        [$foreignQuery] = $this->ownedTargets($otherUser)[LibraryTargetType::ResearchQuery->value];
        app(CreateFavorite::class)->handle($otherUser, $foreignQuery, $foreignProject, 'Foreign secret note');

        $queries = app(LibraryQueries::class);
        $this->assertEquals(
            [$queryFavorite->id],
            $queries->favorites($owner, new LibraryFilters(targetType: LibraryTargetType::ResearchQuery))->pluck('id')->all(),
        );
        $this->assertEquals(
            [$queryFavorite->id],
            $queries->favorites($owner, new LibraryFilters(projectId: $project->id))->pluck('id')->all(),
        );
        $this->assertEquals(
            [$queryFavorite->id],
            $queries->favorites($owner, new LibraryFilters(tagId: $tag->id))->pluck('id')->all(),
        );
        $this->assertEquals(
            [$runFavorite->id],
            $queries->favorites($owner, new LibraryFilters(search: 'Beta'))->pluck('id')->all(),
        );

        foreach ([
            ['project' => $foreignProject->public_id],
            ['tag' => $foreignTag->public_id],
        ] as $filter) {
            $this->actingAs($owner)
                ->get(route('library.favorites.index', $filter))
                ->assertOk()
                ->assertInertia(fn (Assert $page): Assert => $page
                    ->has('favorites', 2)
                    ->has('library.projects', 1)
                    ->has('library.tags', 1)
                )
                ->assertDontSee('Foreign secret project')
                ->assertDontSee('Foreign secret tag')
                ->assertDontSee('Foreign secret note');
        }
    }

    public function test_cross_user_mutations_are_forbidden_without_changing_owner_records(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $project = app(CreateProject::class)->handle($owner, 'Private project');
        [$query, $queryReference] = $this->ownedTargets($owner)[LibraryTargetType::ResearchQuery->value];
        $favorite = app(CreateFavorite::class)->handle($owner, $query, $project, 'Private note');
        $tag = app(CreateTag::class)->handle($owner, 'Private tag');
        app(AttachTag::class)->handle($owner, $tag, $query);

        $this->actingAs($otherUser)->get(route('library.projects.show', $project))->assertForbidden();
        $this->actingAs($otherUser)->patch(route('library.projects.update', $project), ['name' => 'Changed'])->assertForbidden();
        $this->actingAs($otherUser)->post(route('library.projects.archive', $project))->assertForbidden();
        $this->actingAs($otherUser)->post(route('library.projects.restore', $project))->assertForbidden();
        $this->actingAs($otherUser)->delete(route('library.projects.destroy', $project))->assertForbidden();
        $this->actingAs($otherUser)->patch(route('library.favorites.update', $favorite), ['note' => 'Changed'])->assertForbidden();
        $this->actingAs($otherUser)->delete(route('library.favorites.destroy', $favorite))->assertForbidden();
        $this->actingAs($otherUser)->patch(route('library.tags.update', $tag), ['name' => 'Changed'])->assertForbidden();
        $this->actingAs($otherUser)->post(route('library.tags.attach', $tag), [
            'target_type' => LibraryTargetType::ResearchQuery->value,
            'target_reference' => $queryReference,
        ])->assertForbidden();
        $this->actingAs($otherUser)->delete(route('library.tags.detach', $tag), [
            'target_type' => LibraryTargetType::ResearchQuery->value,
            'target_reference' => $queryReference,
        ])->assertForbidden();
        $this->actingAs($otherUser)->delete(route('library.tags.destroy', $tag))->assertForbidden();

        $this->assertDatabaseHas('research_projects', [
            'id' => $project->id,
            'name' => 'Private project',
            'archived_at' => null,
        ]);
        $this->assertDatabaseHas('favorites', [
            'id' => $favorite->id,
            'note' => 'Private note',
        ]);
        $this->assertDatabaseHas('tags', [
            'id' => $tag->id,
            'name' => 'Private tag',
        ]);
        $this->assertDatabaseCount('taggables', 1);
    }

    /**
     * @return array<string, array{Model, string}>
     */
    private function ownedTargets(User $user): array
    {
        $query = app(CreateResearchQuery::class)->handle($user, $this->market(), 'owned query '.$user->id);
        $run = app(CreateResearchRun::class)->handle($user, $query, 25);
        $channel = Channel::query()->create([
            'provider' => 'youtube',
            'provider_channel_id' => 'owned-channel-'.$user->id,
            'title' => 'Owned channel '.$user->id,
        ]);
        $video = Video::query()->create([
            'provider' => 'youtube',
            'provider_video_id' => 'owned-video-'.$user->id,
            'channel_id' => $channel->id,
            'title' => 'Owned video '.$user->id,
            'published_at' => now(),
        ]);
        $run->videos()->attach($video->id, [
            'result_rank' => 1,
            'page_number' => 1,
            'provider_order' => 1,
        ]);
        $discoveryRun = app(CreateDiscoveryRun::class)->handle($user, $this->market(), ['owned seed '.$user->id]);
        $candidate = $discoveryRun->candidates()->create([
            'phrase' => 'owned candidate '.$user->id,
            'cluster_key' => 'owned-cluster-'.$user->id,
            'summary' => 'Owned candidate evidence.',
            'evidence' => ['observed_signal' => 'returned_video_breakout'],
            'status' => NicheCandidateStatus::New,
        ]);

        return [
            LibraryTargetType::NicheCandidate->value => [$candidate, $candidate->public_id],
            LibraryTargetType::Video->value => [$video, $video->provider_video_id],
            LibraryTargetType::Channel->value => [$channel, $channel->provider_channel_id],
            LibraryTargetType::ResearchQuery->value => [$query, $query->public_id],
            LibraryTargetType::ResearchRun->value => [$run, $run->public_id],
        ];
    }

    private function market(): Market
    {
        return Market::query()->where('key', 'global_en')->firstOrFail();
    }
}
