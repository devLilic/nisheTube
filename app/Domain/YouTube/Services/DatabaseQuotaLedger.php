<?php

namespace App\Domain\YouTube\Services;

use App\Domain\YouTube\Contracts\QuotaLedger;
use App\Domain\YouTube\Data\QuotaAttempt;
use App\Domain\YouTube\Data\QuotaBucketSummary;
use App\Domain\YouTube\Data\QuotaSummary;
use App\Domain\YouTube\Enums\QuotaUsageOutcome;
use App\Domain\YouTube\Enums\YouTubeErrorCode;
use App\Models\ApiUsageEvent;
use App\Models\ResearchRun;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;

class DatabaseQuotaLedger implements QuotaLedger
{
    public function __construct(private readonly YouTubeConfiguration $configuration) {}

    public function begin(QuotaAttempt $attempt): int
    {
        $collectionRunId = $attempt->collectionRunId;

        if ($collectionRunId === null && $attempt->researchRunId !== null) {
            $collectionRunId = ResearchRun::query()
                ->whereKey($attempt->researchRunId)
                ->value('collection_run_id');
        }

        $event = ApiUsageEvent::query()->create([
            'user_id' => $attempt->userId,
            'research_run_id' => $attempt->researchRunId,
            'collection_run_id' => $collectionRunId,
            'provider' => $attempt->provider,
            'quota_bucket' => $attempt->bucket,
            'endpoint' => $attempt->endpoint,
            'request_count' => 1,
            'estimated_cost' => $attempt->estimatedCost,
            'outcome' => QuotaUsageOutcome::Attempted,
            'safe_error_code' => null,
            'occurred_at' => Date::now(),
        ]);

        return $event->id;
    }

    public function complete(
        int $eventId,
        QuotaUsageOutcome $outcome,
        ?YouTubeErrorCode $errorCode = null,
    ): void {
        ApiUsageEvent::query()->whereKey($eventId)->update([
            'outcome' => $outcome,
            'safe_error_code' => $errorCode?->value,
        ]);
    }

    public function summary(?CarbonImmutable $at = null): QuotaSummary
    {
        $at ??= CarbonImmutable::instance(Date::now());
        $pacificNow = $at->setTimezone($this->configuration->quotaResetTimezone);
        $start = $pacificNow->startOfDay()->utc();
        $resetAt = $pacificNow->addDay()->startOfDay()->utc();

        $events = ApiUsageEvent::query()
            ->where('provider', 'youtube')
            ->where('occurred_at', '>=', $start)
            ->where('occurred_at', '<', $resetAt)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->get();

        $buckets = [];

        foreach ($this->configuration->bucketAllowances as $bucket => $allowance) {
            $bucketEvents = $events->where('quota_bucket', $bucket);
            $used = (int) $bucketEvents->sum('estimated_cost');
            /** @var ApiUsageEvent|null $last */
            $last = $bucketEvents->first();
            $providerExhausted = $bucketEvents->contains(
                fn (ApiUsageEvent $event): bool => $event->safe_error_code === YouTubeErrorCode::QuotaExhausted->value,
            );

            $buckets[] = new QuotaBucketSummary(
                bucket: $bucket,
                allowance: $allowance,
                used: $used,
                remaining: max(0, $allowance - $used),
                exhausted: $providerExhausted || $used >= $allowance,
                lastEndpoint: $last?->endpoint,
                lastCost: $last?->estimated_cost,
                lastOccurredAt: $last === null ? null : CarbonImmutable::instance($last->occurred_at),
                lastOutcome: $last?->outcome->value,
            );
        }

        return new QuotaSummary($at->utc(), $resetAt, $buckets);
    }
}
