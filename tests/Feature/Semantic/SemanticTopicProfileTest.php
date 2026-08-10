<?php

namespace Tests\Feature\Semantic;

use App\Domain\Analyzer\Actions\CreateAnalyzerRun;
use App\Domain\Analyzer\Enums\AnalyzerVideoRole;
use App\Domain\Collection\Enums\CollectionCachePolicy;
use App\Domain\Semantic\Actions\CalculateSemanticTopicProfile;
use App\Http\ViewModels\AnalyzerRunViewModel;
use App\Models\AnalyzerRunVideo;
use App\Models\Channel;
use App\Models\ChannelSnapshot;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoSnapshot;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class SemanticTopicProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_is_owner_scoped_immutable_idempotent_and_visible_as_inferred(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $run = app(CreateAnalyzerRun::class)->handleTarget(
            $owner,
            'channel',
            'semantic-channel',
            CollectionCachePolicy::AllowFreshCache,
        );
        $channel = Channel::query()->create([
            'provider' => 'youtube',
            'provider_channel_id' => 'semantic-channel',
            'title' => 'Storage Lab',
        ]);
        $channelSnapshot = ChannelSnapshot::query()->create([
            'channel_id' => $channel->id,
            'collection_run_id' => $run->collection_run_id,
            'subscriber_count_hidden' => false,
            'collected_at' => now(),
        ]);
        $run->update(['channel_id' => $channel->id, 'channel_snapshot_id' => $channelSnapshot->id]);

        foreach (['Small apartment storage ideas', 'Small apartment storage makeover', 'Small apartment organization guide'] as $index => $title) {
            $video = Video::query()->create([
                'provider' => 'youtube',
                'provider_video_id' => "semantic-video-{$index}",
                'channel_id' => $channel->id,
                'title' => $title,
                'published_at' => now()->subDays($index + 1),
            ]);
            $snapshot = VideoSnapshot::query()->create([
                'video_id' => $video->id,
                'collection_run_id' => $run->collection_run_id,
                'collected_at' => now(),
            ]);
            AnalyzerRunVideo::query()->create([
                'analyzer_run_id' => $run->id,
                'video_id' => $video->id,
                'video_snapshot_id' => $snapshot->id,
                'channel_snapshot_id' => $channelSnapshot->id,
                'role' => AnalyzerVideoRole::ChannelRecentUpload,
                'source_position' => $index + 1,
            ]);
        }

        $profile = app(CalculateSemanticTopicProfile::class)->handle($run);
        $sameProfile = app(CalculateSemanticTopicProfile::class)->handle($run);

        $this->assertSame($profile->id, $sameProfile->id);
        $this->assertSame($owner->id, $profile->user_id);
        $this->assertSame('inferred', $profile->provenance);
        $this->assertSame('semantic-title-terms-v1', $profile->algorithm_version);
        $this->assertSame('Small Apartment', $profile->niche_label);
        $this->assertDatabaseCount('semantic_topic_profiles', 1);
        $this->assertDatabaseHas('semantic_classifications', ['semantic_topic_profile_id' => $profile->id, 'kind' => 'topic']);

        $payload = app(AnalyzerRunViewModel::class)->toArray($run->fresh());
        $this->assertSame('inferred', $payload['topic_profile']['provenance']);
        $this->assertSame('Small Apartment', $payload['topic_profile']['niche']['label']);
        $this->assertSame(3, $payload['topic_profile']['evidence_video_count']);

        $secondRun = app(CreateAnalyzerRun::class)->handleTarget(
            $owner,
            'channel',
            'semantic-channel',
            CollectionCachePolicy::AllowFreshCache,
        );
        $secondRun->update(['channel_id' => $channel->id, 'channel_snapshot_id' => $channelSnapshot->id]);
        foreach ($run->videoMemberships()->get() as $membership) {
            AnalyzerRunVideo::query()->create([
                'analyzer_run_id' => $secondRun->id,
                'video_id' => $membership->video_id,
                'video_snapshot_id' => $membership->video_snapshot_id,
                'channel_snapshot_id' => $membership->channel_snapshot_id,
                'role' => $membership->role,
                'source_position' => $membership->source_position,
            ]);
        }
        $secondProfile = app(CalculateSemanticTopicProfile::class)->handle($secondRun);
        $this->assertNotSame($profile->id, $secondProfile->id);
        $this->assertSame($profile->algorithm_version, $secondProfile->algorithm_version);
        $this->assertDatabaseCount('semantic_topic_profiles', 2);

        $this->actingAs($owner)->get(route('explore.index', ['entity_type' => 'channel', 'topic' => 'Small Apartment']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('results.total', 1)
                ->where('results.data.0.detected_topic_profile.niche', 'Small Apartment')
                ->where('results.data.0.detected_topic_profile.version', 'semantic-title-terms-v1'));
        $this->actingAs($other)->get(route('explore.index', ['entity_type' => 'channel', 'topic' => 'Small Apartment']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page->where('results.total', 0));

        $this->actingAs($other)->get(route('analyzer.runs.show', $run))->assertForbidden();

        $this->expectException(DomainException::class);
        $profile->update(['language' => 'ru']);
    }
}
