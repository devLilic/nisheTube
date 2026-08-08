<?php

namespace Tests\Feature\Catalog;

use App\Domain\Catalog\ReadModels\BuildResearchRunAnalysis;
use App\Domain\Research\Actions\CreateResearchQuery;
use App\Domain\Research\Actions\CreateResearchRun;
use App\Models\Channel;
use App\Models\Market;
use App\Models\ResearchRun;
use App\Models\User;
use App\Models\Video;
use Database\Seeders\MarketSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ResearchRunAnalysisInterfaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MarketSeeder::class);
    }

    public function test_owner_sees_snapshot_backed_aggregates_search_rows_and_missing_metric_counts(): void
    {
        $owner = User::factory()->create();
        $run = $this->newRun($owner, 4);
        $firstChannel = $this->channel($run, 'channel-one', 'Atelier foarte lung pentru cercetare', 500, false, 40);
        $secondChannel = $this->channel($run, 'channel-two', 'Канал об узких нишах', null, true, 20);

        $this->video($run, $firstChannel, 'video-one', 'Small apartment ideas', 1, 1000, 100, 50, 500, 2);
        $this->video($run, $firstChannel, 'video-two', 'Mobilier pentru spații înguste', 2, 3000, null, 30, 1500, null);
        $this->video($run, $secondChannel, 'video-three', 'Идеи для маленькой квартиры', 3, 5000, 250, 50, 2500, 10);

        $response = $this->actingAs($owner)->get(route('research.runs.show', $run));

        $response->assertOk()->assertInertia(fn (Assert $page): Assert => $page
            ->component('research/show')
            ->where('run.analysis.summary.video_count', 3)
            ->where('run.analysis.summary.channel_count', 2)
            ->where('run.analysis.summary.coverage_percent', 75)
            ->where('run.analysis.summary.median_views', 3000)
            ->where('run.analysis.summary.median_views_per_day', 1500)
            ->where('run.analysis.summary.median_subscribers', 500)
            ->where('run.analysis.summary.median_reach_ratio', 6)
            ->where('run.analysis.summary.median_engagement_rate', 10.5)
            ->where('run.analysis.summary.hidden_subscriber_channels', 1)
            ->where('run.analysis.summary.missing_video_metric_count', 1)
            ->where('run.analysis.summary.latest_collected_at', '2026-08-08T12:00:00+00:00')
            ->has('run.analysis.videos', 3)
            ->where('run.analysis.videos.1.title', 'Mobilier pentru spații înguste')
            ->where('run.analysis.videos.1.like_count', null)
            ->where('run.analysis.videos.1.engagement_rate', null)
            ->has('run.analysis.channels', 2)
            ->where('run.analysis.channels.0.title', 'Канал об узких нишах')
            ->where('run.analysis.channels.0.subscriber_count_hidden', true)
        );
    }

    public function test_analysis_requires_authentication_and_never_exposes_another_users_snapshot_rows(): void
    {
        $owner = User::factory()->create();
        $foreignUser = User::factory()->create();
        $ownerRun = $this->newRun($owner, 1);
        $foreignRun = $this->newRun($foreignUser, 1);
        $ownerChannel = $this->channel($ownerRun, 'owner-channel', 'Owner channel', 500, false, 12);
        $foreignChannel = $this->channel($foreignRun, 'foreign-channel', 'Foreign private channel', 900, false, 30);

        $this->video($ownerRun, $ownerChannel, 'owner-video', 'Owner analysis row', 1, 1000, 50, 5, 500, 2);
        $this->video($foreignRun, $foreignChannel, 'foreign-video', 'Foreign private row', 1, 9000, 500, 50, 4500, 10);

        $this->get(route('research.runs.show', $ownerRun))
            ->assertRedirect(route('login'));

        $this->actingAs($foreignUser)
            ->get(route('research.runs.show', $ownerRun))
            ->assertForbidden();

        $this->actingAs($owner)
            ->get(route('research.runs.show', $ownerRun))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->has('run.analysis.videos', 1)
                ->where('run.analysis.videos.0.title', 'Owner analysis row')
                ->has('run.analysis.channels', 1)
                ->where('run.analysis.channels.0.title', 'Owner channel')
            )
            ->assertDontSee('Foreign private row')
            ->assertDontSee('Foreign private channel');
    }

    public function test_analysis_query_count_remains_constant_for_a_configured_large_sample(): void
    {
        $run = $this->newRun(User::factory()->create(), 50);
        $channel = $this->channel($run, 'shared-channel', 'Shared research channel', 10000, false, 120);

        for ($number = 1; $number <= 50; $number++) {
            $this->video(
                $run,
                $channel,
                "performance-video-{$number}",
                "Performance row {$number}",
                $number,
                $number * 100,
                $number * 5,
                $number,
                $number * 50,
                $number,
            );
        }

        DB::enableQueryLog();
        DB::flushQueryLog();

        $analysis = app(BuildResearchRunAnalysis::class)->handle($run);
        $queries = DB::getQueryLog();

        DB::disableQueryLog();

        $this->assertCount(1, $queries, 'The analysis read model must not add per-video or per-channel queries.');
        $this->assertSame(50, $analysis['summary']['video_count']);
        $this->assertSame(1, $analysis['summary']['channel_count']);
        $this->assertCount(50, $analysis['videos']);
        $this->assertCount(1, $analysis['channels']);
    }

    private function newRun(User $user, int $requestedResultCount): ResearchRun
    {
        $query = app(CreateResearchQuery::class)->handle(
            user: $user,
            market: Market::query()->where('key', 'global_en')->firstOrFail(),
            queryText: 'compact living research',
        );

        return app(CreateResearchRun::class)->handle($user, $query, $requestedResultCount);
    }

    private function channel(
        ResearchRun $run,
        string $providerId,
        string $title,
        ?int $subscribers,
        bool $hidden,
        int $videoCount,
    ): Channel {
        $channel = Channel::query()->create([
            'provider' => 'youtube',
            'provider_channel_id' => $providerId,
            'title' => $title,
            'custom_url' => "@{$providerId}",
            'country' => 'RO',
        ]);

        $run->channelSnapshots()->create([
            'channel_id' => $channel->id,
            'subscriber_count' => $subscribers,
            'view_count' => 100000,
            'video_count' => $videoCount,
            'subscriber_count_hidden' => $hidden,
            'metadata' => ['published_at' => '2024-08-08T12:00:00Z'],
            'collected_at' => '2026-08-08 12:00:00',
        ]);

        return $channel;
    }

    private function video(
        ResearchRun $run,
        Channel $channel,
        string $providerId,
        string $title,
        int $rank,
        int $views,
        ?int $likes,
        ?int $comments,
        int $viewsPerDay,
        ?int $reachRatio,
    ): void {
        $video = Video::query()->create([
            'provider' => 'youtube',
            'provider_video_id' => $providerId,
            'channel_id' => $channel->id,
            'title' => $title,
            'published_at' => '2026-08-06 12:00:00',
            'duration_seconds' => 625,
            'category_id' => '26',
            'is_short' => false,
        ]);

        $run->videos()->attach($video->id, [
            'result_rank' => $rank,
            'page_number' => 1,
            'provider_order' => $rank,
        ]);
        $run->videoSnapshots()->create([
            'video_id' => $video->id,
            'view_count' => $views,
            'like_count' => $likes,
            'comment_count' => $comments,
            'age_seconds' => 172800,
            'views_per_day' => $viewsPerDay,
            'views_to_subscribers_ratio' => $reachRatio,
            'collected_at' => '2026-08-08 12:00:00',
        ]);
    }
}
