<?php

namespace Tests\Feature\YouTube;

use App\Domain\YouTube\Contracts\QuotaLedger;
use App\Domain\YouTube\Data\QuotaAttempt;
use App\Domain\YouTube\Enums\QuotaUsageOutcome;
use App\Models\ApiUsageEvent;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuotaLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_is_project_wide_and_uses_the_pacific_reset_boundary(): void
    {
        config()->set('youtube.quota_buckets.search.allowance', 3);
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();

        ApiUsageEvent::query()->create($this->eventAttributes(
            userId: $firstUser->id,
            occurredAt: '2026-08-07T06:59:59Z',
        ));
        ApiUsageEvent::query()->create($this->eventAttributes(
            userId: $firstUser->id,
            occurredAt: '2026-08-07T07:00:00Z',
        ));
        ApiUsageEvent::query()->create($this->eventAttributes(
            userId: $secondUser->id,
            occurredAt: '2026-08-07T08:00:00Z',
        ));

        $summary = app(QuotaLedger::class)->summary(
            new CarbonImmutable('2026-08-07T12:00:00Z'),
        );

        $this->assertSame(2, $summary->buckets[0]->used);
        $this->assertSame(1, $summary->buckets[0]->remaining);
        $this->assertSame('2026-08-08T07:00:00+00:00', $summary->resetAt->toIso8601String());
        $this->assertSame('search.list', $summary->buckets[0]->lastEndpoint);
        $this->assertArrayNotHasKey('api_key', $summary->toSafeArray());
    }

    public function test_begin_records_an_attempt_before_completion(): void
    {
        $user = User::factory()->create();
        $ledger = app(QuotaLedger::class);

        $eventId = $ledger->begin(new QuotaAttempt(
            provider: 'youtube',
            bucket: 'search',
            endpoint: 'search.list',
            estimatedCost: 1,
            userId: $user->id,
        ));

        $this->assertDatabaseHas('api_usage_events', [
            'id' => $eventId,
            'outcome' => QuotaUsageOutcome::Attempted->value,
        ]);

        $ledger->complete($eventId, QuotaUsageOutcome::Succeeded);

        $this->assertDatabaseHas('api_usage_events', [
            'id' => $eventId,
            'outcome' => QuotaUsageOutcome::Succeeded->value,
        ]);
    }

    public function test_summary_marks_a_bucket_exhausted_at_the_exact_allowance_boundary(): void
    {
        config()->set('youtube.quota_buckets.search.allowance', 2);
        $user = User::factory()->create();

        ApiUsageEvent::query()->create($this->eventAttributes(
            userId: $user->id,
            occurredAt: '2026-08-07T08:00:00Z',
        ));
        ApiUsageEvent::query()->create($this->eventAttributes(
            userId: $user->id,
            occurredAt: '2026-08-07T09:00:00Z',
        ));

        $summary = app(QuotaLedger::class)->summary(
            new CarbonImmutable('2026-08-07T12:00:00Z'),
        );

        $this->assertSame(2, $summary->buckets[0]->used);
        $this->assertSame(0, $summary->buckets[0]->remaining);
        $this->assertTrue($summary->buckets[0]->exhausted);
    }

    /** @return array<string, mixed> */
    private function eventAttributes(int $userId, string $occurredAt): array
    {
        return [
            'user_id' => $userId,
            'provider' => 'youtube',
            'quota_bucket' => 'search',
            'endpoint' => 'search.list',
            'request_count' => 1,
            'estimated_cost' => 1,
            'outcome' => QuotaUsageOutcome::Succeeded,
            'occurred_at' => $occurredAt,
        ];
    }
}
