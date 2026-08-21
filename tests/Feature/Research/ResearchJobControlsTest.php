<?php

namespace Tests\Feature\Research;

use App\Domain\Navigation\Services\CompletedRunNotifications;
use App\Domain\Research\Actions\CreateResearchQuery;
use App\Domain\Research\Actions\CreateResearchRun;
use App\Domain\Research\Actions\TransitionResearchRun;
use App\Domain\Research\Enums\ResearchRunStatus;
use App\Domain\Settings\Enums\MarketKey;
use App\Jobs\Research\CollectResearchRunSearch;
use App\Models\Market;
use App\Models\ResearchRun;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\MarketSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ResearchJobControlsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MarketSeeder::class);
    }

    public function test_only_the_owner_can_cancel_a_queued_run_and_cancellation_never_calls_youtube(): void
    {
        $owner = User::factory()->create();
        $run = $this->queuedRun($owner);

        $this->actingAs(User::factory()->create())
            ->post(route('research.runs.cancel', $run))
            ->assertForbidden();

        $this->actingAs($owner)
            ->post(route('research.runs.cancel', $run))
            ->assertRedirect(route('research.runs.show', $run));

        $run->refresh();

        $this->assertSame(ResearchRunStatus::Cancelled, $run->status);
        $this->assertSame('research_cancelled', $run->error_code);
        $this->assertNotNull($run->failed_at);
        $this->assertSame('failed', $run->collectionRun->fresh()->status->value);
        $this->assertDatabaseCount('api_usage_events', 0);

        Http::fake();
        app()->call([new CollectResearchRunSearch($run->id), 'handle']);

        Http::assertNothingSent();
        $this->assertDatabaseCount('api_usage_events', 0);

        $this->actingAs($owner)
            ->post(route('research.runs.cancel', $run))
            ->assertRedirect(route('research.runs.show', $run));
        $this->assertSame(ResearchRunStatus::Cancelled, $run->fresh()->status);

        $notifications = app(CompletedRunNotifications::class)->for($owner);
        $this->assertSame('Queued research cancelled', $notifications['items'][0]['title']);
        $this->assertStringContainsString('no YouTube requests were made', $notifications['items'][0]['description']);
    }

    public function test_queued_runs_show_cancellation_and_worker_feedback_after_the_grace_period(): void
    {
        CarbonImmutable::setTestNow('2026-08-21 10:00:00 UTC');

        try {
            $owner = User::factory()->create();
            $run = $this->queuedRun($owner);

            CarbonImmutable::setTestNow('2026-08-21 10:02:01 UTC');

            $this->actingAs($owner)
                ->get(route('research.runs.show', $run))
                ->assertOk()
                ->assertInertia(fn (Assert $page): Assert => $page
                    ->component('research/show')
                    ->where('run.status', 'queued')
                    ->where('run.is_active', true)
                    ->where('run.job_control.state', 'worker_unavailable')
                    ->where('run.job_control.can_cancel', true)
                    ->where('run.job_control.description', fn (string $value): bool => str_contains($value, 'may not be running')),
                );
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    private function queuedRun(User $user): ResearchRun
    {
        $query = app(CreateResearchQuery::class)->handle(
            user: $user,
            market: Market::query()->where('key', MarketKey::GlobalEnglish->value)->firstOrFail(),
            queryText: 'queue control research',
        );
        $run = app(CreateResearchRun::class)->handle($user, $query, 50);

        return app(TransitionResearchRun::class)->handle($run, ResearchRunStatus::Queued);
    }
}
