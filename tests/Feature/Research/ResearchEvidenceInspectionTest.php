<?php

namespace Tests\Feature\Research;

use App\Domain\Research\Actions\CreateResearchQuery;
use App\Domain\Research\Actions\CreateResearchRun;
use App\Models\ApiUsageEvent;
use App\Models\Channel;
use App\Models\Market;
use App\Models\ResearchRun;
use App\Models\User;
use App\Models\Video;
use Database\Seeders\MarketSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ResearchEvidenceInspectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(MarketSeeder::class);
    }

    public function test_evidence_is_server_bounded_sorted_filtered_and_exact(): void
    {
        Http::preventStrayRequests();
        $owner = User::factory()->create();
        $run = $this->newRun($owner, 15);
        $small = $this->channel($run, 'small-channel', 'Canal românesc foarte lung pentru cercetarea nișelor', 50000);
        $large = $this->channel($run, 'large-channel', 'Большой исследовательский канал', 2000000);

        for ($rank = 1; $rank <= 12; $rank++) {
            $this->video(
                $run,
                $small,
                "small-video-{$rank}",
                "Titlu foarte lung cu dovezi românești și exacte {$rank}",
                $rank,
                $rank * 100,
                $rank * 10,
                isShort: $rank === 12,
            );
        }
        $this->video($run, $large, 'large-video', 'Large channel evidence', 13, 99999, 9999);

        $usageBefore = ApiUsageEvent::query()->count();

        $this->actingAs($owner)
            ->get(route('research.runs.show', [
                'researchRun' => $run,
                'evidence_filter' => 'small_channels',
                'evidence_sort' => 'views_per_day',
                'evidence_direction' => 'desc',
                'evidence_page' => 1,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('research/show')
                ->where('run.evidence_inspection.query.filter', 'small_channels')
                ->where('run.evidence_inspection.query.sort', 'views_per_day')
                ->where('run.evidence_inspection.query.direction', 'desc')
                ->where('run.evidence_inspection.pagination.page_size', 10)
                ->where('run.evidence_inspection.pagination.total', 12)
                ->where('run.evidence_inspection.pagination.last_page', 2)
                ->has('run.evidence_inspection.items', 10)
                ->where('run.evidence_inspection.items.0.provider_video_id', 'small-video-12')
                ->where('run.evidence_inspection.items.0.views_per_day', 120)
                ->where('run.evidence_inspection.items.0.engagement_rate', 11)
                ->where('run.evidence_inspection.items.0.subscriber_count', 50000)
                ->where('run.evidence_inspection.items.0.format', 'shorts')
                ->where('run.evidence_inspection.items.0.metrics_complete', true)
                ->where('run.evidence_inspection.relevance.state', 'provider_order_only')
                ->where('run.evidence_inspection.filters.1.enabled', false)
            );

        $this->assertSame($usageBefore, ApiUsageEvent::query()->count());
    }

    public function test_evidence_queries_and_prefilled_handoffs_are_owner_scoped(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $run = $this->newRun($owner, 1);
        $channel = $this->channel($run, 'owner-channel', 'Owner channel', 1000);
        $this->video($run, $channel, 'owner-video', 'Owner-only evidence', 1, 1000, 100);

        $this->get(route('research.runs.show', $run))->assertRedirect(route('login'));
        $this->actingAs($other)->get(route('research.runs.show', $run))->assertForbidden();

        $this->actingAs($other)
            ->get(route('research.create', ['repeat' => $run->public_id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('repeat_source', null)
            );

        $this->actingAs($owner)
            ->get(route('research.create', ['repeat' => $run->public_id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('repeat_source.query_text', 'bounded evidence research')
                ->where('repeat_source.market_key', 'global_en')
            );

        $this->actingAs($other)
            ->get(route('exports.index', ['run' => $run->public_id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('selected_run_id', null)
            );
    }

    public function test_invalid_evidence_inputs_are_rejected_without_running_a_query_variant(): void
    {
        $owner = User::factory()->create();
        $run = $this->newRun($owner, 1);

        $this->actingAs($owner)
            ->from(route('research.runs.show', $run))
            ->get(route('research.runs.show', [
                'researchRun' => $run,
                'evidence_sort' => 'raw_sql',
                'evidence_filter' => 'foreign',
                'evidence_page' => 10001,
            ]))
            ->assertRedirect(route('research.runs.show', $run))
            ->assertSessionHasErrors(['evidence_sort', 'evidence_filter', 'evidence_page']);
    }

    private function newRun(User $user, int $requested): ResearchRun
    {
        $query = app(CreateResearchQuery::class)->handle(
            user: $user,
            market: Market::query()->where('key', 'global_en')->firstOrFail(),
            queryText: 'bounded evidence research',
        );

        return app(CreateResearchRun::class)->handle($user, $query, $requested);
    }

    private function channel(ResearchRun $run, string $providerId, string $title, int $subscribers): Channel
    {
        $channel = Channel::query()->create([
            'provider' => 'youtube',
            'provider_channel_id' => $providerId,
            'title' => $title,
        ]);
        $run->channelSnapshots()->create([
            'channel_id' => $channel->id,
            'collection_run_id' => $run->collection_run_id,
            'subscriber_count' => $subscribers,
            'view_count' => 100000,
            'video_count' => 100,
            'subscriber_count_hidden' => false,
            'collected_at' => '2026-08-15 10:00:00',
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
        int $viewsPerDay,
        bool $isShort = false,
    ): void {
        $video = Video::query()->create([
            'provider' => 'youtube',
            'provider_video_id' => $providerId,
            'channel_id' => $channel->id,
            'title' => $title,
            'published_at' => '2026-08-14 10:00:00',
            'duration_seconds' => $isShort ? 45 : 900,
            'is_short' => $isShort,
        ]);
        $run->videos()->attach($video->id, [
            'result_rank' => $rank,
            'page_number' => 1,
            'provider_order' => $rank,
        ]);
        $snapshot = $run->videoSnapshots()->create([
            'video_id' => $video->id,
            'collection_run_id' => $run->collection_run_id,
            'view_count' => $views,
            'like_count' => (int) ($views * 0.1),
            'comment_count' => (int) ($views * 0.01),
            'age_seconds' => 86400,
            'views_per_day' => $viewsPerDay,
            'views_to_subscribers_ratio' => 0.25,
            'collected_at' => '2026-08-15 10:00:00',
        ]);
        $run->videoMemberships()->where('video_id', $video->id)->firstOrFail()->pinSources(
            $snapshot,
            $run->channelSnapshots()->where('channel_id', $channel->id)->firstOrFail(),
        );
    }
}
