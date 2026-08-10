<?php

namespace Tests\Feature\Library;

use App\Domain\Discovery\Actions\CreateDiscoveryRun;
use App\Domain\Discovery\Enums\NicheCandidateStatus;
use App\Domain\Library\Actions\AttachTag;
use App\Domain\Library\Actions\CreateFavorite;
use App\Domain\Library\Actions\CreateProject;
use App\Domain\Library\Actions\CreateTag;
use App\Domain\Library\Actions\DeleteFavorite;
use App\Domain\Library\Actions\DeleteProject;
use App\Domain\Library\Actions\DeleteTag;
use App\Domain\Library\Actions\DetachTag;
use App\Domain\Library\Actions\SetProjectArchived;
use App\Domain\Library\Actions\UpdateFavorite;
use App\Domain\Library\Actions\UpdateProject;
use App\Domain\Library\Actions\UpdateTag;
use App\Domain\Research\Actions\CreateResearchQuery;
use App\Domain\Research\Actions\CreateResearchRun;
use App\Models\AnalyzerRun;
use App\Models\Channel;
use App\Models\Market;
use App\Models\NicheCandidate;
use App\Models\ResearchQuery;
use App\Models\ResearchRun;
use App\Models\User;
use App\Models\Video;
use App\Models\WatchlistItem;
use Database\Seeders\MarketSeeder;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LibrarySchemaDomainTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MarketSeeder::class);
    }

    public function test_library_schema_and_morph_map_match_the_documented_allow_list(): void
    {
        $this->assertTrue(Schema::hasColumns('favorites', [
            'public_id', 'user_id', 'research_project_id', 'target_type', 'target_id', 'note',
        ]));
        $this->assertTrue(Schema::hasIndex('favorites', ['user_id', 'target_type', 'target_id'], 'unique'));
        $this->assertTrue(Schema::hasColumns('tags', [
            'public_id', 'user_id', 'name', 'name_key', 'color',
        ]));
        $this->assertTrue(Schema::hasIndex('tags', ['user_id', 'name_key'], 'unique'));
        $this->assertTrue(Schema::hasColumns('taggables', ['tag_id', 'target_type', 'target_id']));
        $this->assertTrue(Schema::hasIndex('taggables', ['tag_id', 'target_type', 'target_id'], 'unique'));
        $this->assertSame([
            'video' => Video::class,
            'channel' => Channel::class,
            'research_query' => ResearchQuery::class,
            'research_run' => ResearchRun::class,
            'niche_candidate' => NicheCandidate::class,
            'analyzer_run' => AnalyzerRun::class,
            'watchlist_item' => WatchlistItem::class,
        ], Relation::morphMap());
    }

    public function test_project_crud_normalization_archive_restore_and_delete_preserve_linked_items(): void
    {
        $user = User::factory()->create();
        $project = app(CreateProject::class)->handle(
            $user,
            '  Compact   homes  ',
            '  A durable project note.  ',
            '#a1b2c3',
        );

        $this->assertSame('Compact homes', $project->name);
        $this->assertSame('A durable project note.', $project->description);
        $this->assertSame('#A1B2C3', $project->color);

        $project = app(UpdateProject::class)->handle($user, $project, 'Updated homes', null, '#112233');
        $this->assertSame('Updated homes', $project->name);
        $this->assertNull($project->description);

        $archived = app(SetProjectArchived::class)->handle($user, $project, true);
        $this->assertNotNull($archived->archived_at);
        $this->assertSame(
            $archived->archived_at->toIso8601String(),
            app(SetProjectArchived::class)->handle($user, $archived, true)->archived_at?->toIso8601String(),
        );
        $restored = app(SetProjectArchived::class)->handle($user, $archived, false);
        $this->assertNull($restored->archived_at);

        $query = app(CreateResearchQuery::class)->handle($user, $this->market(), 'linked query', $restored);
        $favorite = app(CreateFavorite::class)->handle($user, $query, $restored, 'Keep this note.');
        app(DeleteProject::class)->handle($user, $restored);

        $this->assertDatabaseMissing('research_projects', ['id' => $project->id]);
        $this->assertNull($query->fresh()->research_project_id);
        $this->assertNull($favorite->fresh()->research_project_id);
        $this->assertSame('Keep this note.', $favorite->fresh()->note);
    }

    public function test_all_allowed_target_types_can_be_favorited_and_resolve_through_the_safe_morph_map(): void
    {
        $user = User::factory()->create();
        [$query, $run, $video, $channel, $candidate] = $this->ownedTargets($user);

        foreach ([$query, $run, $video, $channel, $candidate] as $target) {
            $favorite = app(CreateFavorite::class)->handle($user, $target, null, 'Evidence note');
            $this->assertTrue($favorite->target->is($target));
        }

        $this->assertDatabaseCount('favorites', 5);

        try {
            app(CreateFavorite::class)->handle($user, $this->market());
            $this->fail('Markets are not an allowed library target.');
        } catch (DomainException $exception) {
            $this->assertSame('This target type cannot be stored in the library.', $exception->getMessage());
        }

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('This item is already saved to favorites.');
        app(CreateFavorite::class)->handle($user, $query);
    }

    public function test_favorites_enforce_target_project_and_owner_boundaries_and_support_notes(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        [$query] = $this->ownedTargets($owner);
        $project = app(CreateProject::class)->handle($owner, 'Owner project');
        $foreignProject = app(CreateProject::class)->handle($otherUser, 'Foreign project');

        $favorite = app(CreateFavorite::class)->handle($owner, $query, $project, 'Initial note');
        $updated = app(UpdateFavorite::class)->handle($owner, $favorite, null, '  Updated note.  ');
        $this->assertNull($updated->research_project_id);
        $this->assertSame('Updated note.', $updated->note);

        app(SetProjectArchived::class)->handle($owner, $project, true);

        try {
            app(UpdateFavorite::class)->handle($owner, $updated, $project, null);
            $this->fail('Archived projects must not accept new library assignments.');
        } catch (AuthorizationException) {
            $this->assertNull($updated->fresh()->research_project_id);
        }

        try {
            app(CreateFavorite::class)->handle($otherUser, $query, $foreignProject);
            $this->fail('Another user must not favorite a private target.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('favorites', 1);
        }

        $this->expectException(AuthorizationException::class);
        app(UpdateFavorite::class)->handle($otherUser, $updated, $foreignProject, 'Foreign change');
    }

    public function test_tags_are_normalized_owner_scoped_idempotently_attached_and_cleaned_with_favorites(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        [$query, $run] = $this->ownedTargets($owner);
        $favorite = app(CreateFavorite::class)->handle($owner, $query);
        $tag = app(CreateTag::class)->handle($owner, '  High   intent ', '#abcdef');

        $this->assertSame('High intent', $tag->name);
        $this->assertSame('high intent', $tag->name_key);
        $this->assertSame('#ABCDEF', $tag->color);

        $first = app(AttachTag::class)->handle($owner, $tag, $query);
        $second = app(AttachTag::class)->handle($owner, $tag, $query);
        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('taggables', 1);
        $this->assertTrue($first->target->is($query));

        try {
            app(AttachTag::class)->handle($owner, $tag, $run);
            $this->fail('Unsaved targets must not be tagged.');
        } catch (DomainException) {
            $this->assertDatabaseCount('taggables', 1);
        }

        $renamed = app(UpdateTag::class)->handle($owner, $tag, 'Priority', null);
        $this->assertSame('priority', $renamed->name_key);

        try {
            app(DetachTag::class)->handle($otherUser, $renamed, $query);
            $this->fail('Foreign users must not detach tags.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('taggables', 1);
        }

        app(DeleteFavorite::class)->handle($owner, $favorite);
        $this->assertDatabaseCount('favorites', 0);
        $this->assertDatabaseCount('taggables', 0);

        app(DeleteTag::class)->handle($owner, $renamed);
        $this->assertDatabaseCount('tags', 0);
    }

    /** @return array{ResearchQuery, ResearchRun, Video, Channel, NicheCandidate} */
    private function ownedTargets(User $user): array
    {
        $market = $this->market();
        $query = app(CreateResearchQuery::class)->handle($user, $market, 'owned target query');
        $run = app(CreateResearchRun::class)->handle($user, $query, 25);
        $channel = Channel::query()->create([
            'provider' => 'youtube',
            'provider_channel_id' => 'channel-'.$user->id,
            'title' => 'Owned channel',
        ]);
        $video = Video::query()->create([
            'provider' => 'youtube',
            'provider_video_id' => 'video-'.$user->id,
            'channel_id' => $channel->id,
            'title' => 'Owned video',
            'published_at' => now(),
        ]);
        $run->videos()->attach($video->id, [
            'result_rank' => 1,
            'page_number' => 1,
            'provider_order' => 1,
        ]);
        $discovery = app(CreateDiscoveryRun::class)->handle($user, $market, ['owned discovery']);
        $candidate = $discovery->candidates()->create([
            'phrase' => 'owned candidate',
            'cluster_key' => 'owned-cluster-'.$user->id,
            'summary' => 'Owned candidate evidence.',
            'evidence' => ['observed_signal' => 'returned_video_breakout'],
            'status' => NicheCandidateStatus::New,
        ]);

        return [$query, $run, $video, $channel, $candidate];
    }

    private function market(): Market
    {
        return Market::query()->where('key', 'global_en')->firstOrFail();
    }
}
