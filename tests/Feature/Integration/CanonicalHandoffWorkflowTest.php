<?php

namespace Tests\Feature\Integration;

use App\Domain\Research\Actions\CreateResearchQuery;
use App\Domain\Research\Actions\CreateResearchRun;
use App\Jobs\Analyzer\CollectAnalyzerRun;
use App\Models\AnalyzerRun;
use App\Models\Channel;
use App\Models\Market;
use App\Models\User;
use App\Models\Video;
use Database\Seeders\MarketSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class CanonicalHandoffWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MarketSeeder::class);
    }

    public function test_search_analyzer_return_context_is_owner_scoped_persisted_and_safe(): void
    {
        Queue::fake();
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $query = app(CreateResearchQuery::class)->handle(
            $owner,
            Market::query()->where('key', 'global_en')->firstOrFail(),
            'compact studio storage',
        );
        $researchRun = app(CreateResearchRun::class)->handle($owner, $query, 25);
        $channel = Channel::query()->create([
            'provider' => 'youtube',
            'provider_channel_id' => 'canonical-channel-01',
            'title' => 'Canonical channel',
        ]);
        $video = Video::query()->create([
            'provider' => 'youtube',
            'provider_video_id' => 'canonical01',
            'channel_id' => $channel->id,
            'title' => 'Canonical video',
            'published_at' => now(),
        ]);
        $researchRun->videos()->attach($video->id, ['result_rank' => 1, 'page_number' => 1, 'provider_order' => 1]);
        $returnTo = "/research/runs/{$researchRun->public_id}?tab=videos";

        $this->actingAs($owner)->post(route('analyzer.store'), [
            'video_reference' => $video->provider_video_id,
            'origin_kind' => 'search',
            'origin_reference' => $researchRun->public_id,
            'return_to' => $returnTo,
        ])->assertRedirect();

        $ownedRun = AnalyzerRun::query()->latest('id')->firstOrFail();
        $this->assertSame('search', $ownedRun->origin_kind);
        $this->assertSame($researchRun->public_id, $ownedRun->origin_reference);
        $this->assertSame(['return_url' => $returnTo], $ownedRun->navigation_context);
        Queue::assertPushed(CollectAnalyzerRun::class);

        $this->actingAs($owner)->get(route('analyzer.runs.show', $ownedRun))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('run.origin.return_url', $returnTo)
                ->has('workspaces'));

        $this->actingAs($other)->post(route('analyzer.store'), [
            'video_reference' => $video->provider_video_id,
            'origin_kind' => 'search',
            'origin_reference' => $researchRun->public_id,
            'return_to' => '//evil.test/steal',
        ])->assertRedirect();

        $foreignRun = AnalyzerRun::query()->latest('id')->firstOrFail();
        $this->assertSame('manual', $foreignRun->origin_kind);
        $this->assertNull($foreignRun->origin_reference);
        $this->assertNull($foreignRun->navigation_context);
    }
}
