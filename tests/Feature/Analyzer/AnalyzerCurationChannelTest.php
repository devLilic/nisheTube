<?php

namespace Tests\Feature\Analyzer;

use App\Domain\Analyzer\Actions\CreateAnalyzerRun;
use App\Domain\Analyzer\Actions\TransitionAnalyzerRun;
use App\Domain\Analyzer\Enums\AnalyzerRunStatus;
use App\Domain\Collection\Enums\CollectionCachePolicy;
use App\Domain\YouTube\Contracts\ChannelUploadsProvider;
use App\Domain\YouTube\Contracts\VideoResearchProvider;
use App\Domain\YouTube\Data\ChannelDetails;
use App\Domain\YouTube\Data\ChannelDetailsBatch;
use App\Domain\YouTube\Data\ChannelUpload;
use App\Domain\YouTube\Data\ChannelUploadsPage;
use App\Domain\YouTube\Data\ChannelUploadsRequest;
use App\Domain\YouTube\Data\VideoDetails;
use App\Domain\YouTube\Data\VideoDetailsBatch;
use App\Domain\YouTube\Data\VideoSearchPage;
use App\Domain\YouTube\Data\VideoSearchRequest;
use App\Domain\YouTube\Data\YouTubeIdBatchRequest;
use App\Jobs\Analyzer\CollectAnalyzerRun;
use App\Models\AnalyzerRun;
use App\Models\Tag;
use App\Models\User;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class AnalyzerCurationChannelTest extends TestCase
{
    use RefreshDatabase;

    public function test_standalone_channel_entry_is_validated_and_duplicate_submissions_create_safe_attempts(): void
    {
        Queue::fake();
        $owner = User::factory()->create();

        foreach (range(1, 2) as $attempt) {
            $this->actingAs($owner)->post(route('analyzer.store'), [
                'target_kind' => 'channel',
                'target_reference' => 'https://www.youtube.com/channel/'.ChannelAnalyzerFakeProvider::CHANNEL_ID,
            ])->assertRedirect();

            $this->assertDatabaseHas('analyzer_runs', [
                'user_id' => $owner->id,
                'target_kind' => 'channel',
                'target_provider_id' => ChannelAnalyzerFakeProvider::CHANNEL_ID,
                'attempt_number' => $attempt,
            ]);
        }

        $this->actingAs($owner)->from(route('analyzer.index'))->post(route('analyzer.store'), [
            'target_kind' => 'channel',
            'target_reference' => 'https://youtube.com.evil.test/channel/'.ChannelAnalyzerFakeProvider::CHANNEL_ID,
        ])->assertSessionHasErrors('target_reference');

        $this->assertDatabaseCount('analyzer_runs', 2);
        $this->assertDatabaseCount('collection_runs', 2);
        Queue::assertPushed(CollectAnalyzerRun::class, 2);
    }

    public function test_channel_route_reuses_shared_collection_and_metric_services_without_video_metrics(): void
    {
        $run = $this->completedChannelRun();

        $this->assertSame('completed', $run->fresh()->status->value);
        $this->assertNotNull($run->channel_snapshot_id);
        $this->assertDatabaseHas('channel_analysis_metrics', [
            'analyzer_run_id' => $run->id,
            'recent_valid_count' => 5,
            'recent_requested_count' => 5,
            'behavior_version' => 'channel-behavior-v1',
        ]);
        $this->assertDatabaseMissing('video_analysis_metrics', ['analyzer_run_id' => $run->id]);
        $this->assertSame(5, $run->videoMemberships()->where('role', 'channel_recent_upload')->count());

        $this->actingAs($run->user)->get(route('analyzer.runs.show', $run))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('analyzer/show')
                ->where('run.target_kind', 'channel')
                ->where('run.video', null)
                ->where('run.channel.provider_channel_id', ChannelAnalyzerFakeProvider::CHANNEL_ID)
                ->where('run.channel_metrics.recent_valid_count', 5)
                ->has('run.recent_videos', 5)
                ->where('run.handoffs.watchlist.available', true)
                ->where('run.handoffs.topic_workspace.available', true));
    }

    public function test_channel_refresh_keeps_the_same_target_kind_and_canonical_id(): void
    {
        Queue::fake();
        $owner = User::factory()->create();
        $source = app(CreateAnalyzerRun::class)->handleTarget(
            $owner,
            'channel',
            ChannelAnalyzerFakeProvider::CHANNEL_ID,
            CollectionCachePolicy::AllowFreshCache,
        );
        $source = app(TransitionAnalyzerRun::class)->handle(
            $source,
            AnalyzerRunStatus::Failed,
            'youtube_channel_not_found',
            'Unavailable.',
        );

        $this->actingAs($owner)->post(route('analyzer.runs.refresh', $source), [
            'mode' => 'force_refresh',
        ])->assertRedirect();

        $refresh = AnalyzerRun::query()->whereKeyNot($source->id)->sole();
        $this->assertSame('channel', $refresh->target_kind);
        $this->assertSame(ChannelAnalyzerFakeProvider::CHANNEL_ID, $refresh->target_provider_id);
        $this->assertSame('refresh', $refresh->origin_kind);
        $this->assertSame($source->public_id, $refresh->origin_reference);
        Queue::assertPushed(CollectAnalyzerRun::class, 1);
    }

    public function test_curation_and_favorite_state_are_owner_isolated_and_duplicate_safe(): void
    {
        $run = $this->completedChannelRun();
        $owner = $run->user;
        $other = User::factory()->create();

        foreach (['First note', 'Updated note'] as $note) {
            $this->actingAs($owner)->patch(route('analyzer.runs.curation.update', $run), [
                'subject_type' => 'channel',
                'research_status' => 'promising',
                'note' => $note,
            ])->assertRedirect();
        }

        $this->assertDatabaseCount('analyzer_curations', 1);
        $this->assertDatabaseHas('analyzer_curations', [
            'user_id' => $owner->id,
            'subject_type' => 'channel',
            'subject_id' => $run->channel_id,
            'research_status' => 'promising',
            'note' => 'Updated note',
        ]);

        $favoritePayload = [
            'target_type' => 'channel',
            'target_reference' => ChannelAnalyzerFakeProvider::CHANNEL_ID,
        ];
        $this->actingAs($owner)->post(route('library.favorites.store'), $favoritePayload)->assertRedirect();
        $this->actingAs($owner)->post(route('library.favorites.store'), $favoritePayload)->assertRedirect();

        $this->assertDatabaseCount('favorites', 1);
        $this->actingAs($owner)->post(route('library.tags.store'), ['name' => 'Promising'])->assertRedirect();
        $tag = Tag::query()->sole();
        $tagPayload = [
            'target_type' => 'channel',
            'target_reference' => ChannelAnalyzerFakeProvider::CHANNEL_ID,
        ];
        $this->actingAs($owner)->post(route('library.tags.attach', $tag), $tagPayload)->assertRedirect();
        $this->actingAs($owner)->post(route('library.tags.attach', $tag), $tagPayload)->assertRedirect();
        $this->assertDatabaseCount('taggables', 1);

        $this->actingAs($other)->patch(route('analyzer.runs.curation.update', $run), [
            'subject_type' => 'channel',
            'research_status' => 'ruled_out',
        ])->assertForbidden();
        $this->actingAs($other)->post(route('library.favorites.store'), [
            'target_type' => 'channel',
            'target_reference' => ChannelAnalyzerFakeProvider::CHANNEL_ID,
        ])->assertNotFound();
        $this->assertDatabaseCount('analyzer_curations', 1);
        $this->assertDatabaseCount('favorites', 1);
        $this->assertDatabaseCount('taggables', 1);
    }

    private function completedChannelRun(): AnalyzerRun
    {
        config(['analyzer.recent_video_limit' => 5]);
        $owner = User::factory()->create();
        $run = app(CreateAnalyzerRun::class)->handleTarget(
            $owner,
            'channel',
            ChannelAnalyzerFakeProvider::CHANNEL_ID,
            CollectionCachePolicy::ForceRefresh,
        );
        $provider = new ChannelAnalyzerFakeProvider;
        $this->app->instance(VideoResearchProvider::class, $provider);
        $this->app->instance(ChannelUploadsProvider::class, $provider);
        app()->call([new CollectAnalyzerRun($run->id), 'handle']);

        return $run->fresh();
    }
}

final class ChannelAnalyzerFakeProvider implements ChannelUploadsProvider, VideoResearchProvider
{
    public const CHANNEL_ID = 'UCabcdefghijklmnopqrstuv';

    public function search(VideoSearchRequest $request): VideoSearchPage
    {
        throw new \LogicException('Channel Analyzer must not call search.list.');
    }

    public function fetchChannels(YouTubeIdBatchRequest $request): ChannelDetailsBatch
    {
        return new ChannelDetailsBatch([new ChannelDetails(
            channelId: self::CHANNEL_ID,
            title: 'Standalone channel',
            customUrl: '@standalone',
            thumbnailUrl: null,
            country: 'RO',
            subscriberCount: 2_000,
            viewCount: 200_000,
            videoCount: 20,
            subscriberCountHidden: false,
            metadata: ['uploads_playlist_id' => 'UUabcdefghijklmnopqrstuv'],
        )], new DateTimeImmutable('2026-08-09 12:00:00 UTC'));
    }

    public function listUploads(ChannelUploadsRequest $request): ChannelUploadsPage
    {
        return new ChannelUploadsPage(array_map(
            static fn (int $position): ChannelUpload => new ChannelUpload(sprintf('chanvid%04d', $position), $position),
            range(1, $request->maxResults),
        ));
    }

    public function fetchVideos(YouTubeIdBatchRequest $request): VideoDetailsBatch
    {
        return new VideoDetailsBatch(array_map(function (string $id): VideoDetails {
            $position = (int) substr($id, -4);

            return new VideoDetails(
                videoId: $id,
                channelId: self::CHANNEL_ID,
                channelTitle: 'Standalone channel',
                title: "Channel upload {$position}",
                thumbnailUrl: null,
                publishedAt: new DateTimeImmutable("2026-08-09 12:00:00 UTC -{$position} days"),
                durationSeconds: 600 + $position,
                categoryId: '26',
                isShort: false,
                viewCount: $position * 1_000,
                likeCount: $position * 100,
                commentCount: $position * 10,
            );
        }, $request->ids), new DateTimeImmutable('2026-08-09 12:00:00 UTC'));
    }
}
