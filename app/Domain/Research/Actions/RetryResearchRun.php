<?php

namespace App\Domain\Research\Actions;

use App\Domain\Collection\Actions\CreateResearchCollectionRun;
use App\Domain\Research\Enums\ResearchRunStatus;
use App\Models\ResearchRun;
use App\Models\User;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class RetryResearchRun
{
    public function __construct(
        private readonly QueueResearchRun $queueRun,
        private readonly CreateResearchCollectionRun $createCollectionRun,
    ) {}

    /** @throws AuthorizationException */
    public function handle(User $user, ResearchRun $failedRun): ResearchRun
    {
        $retry = DB::transaction(function () use ($user, $failedRun): ResearchRun {
            $lockedRun = ResearchRun::query()->lockForUpdate()->findOrFail($failedRun->id);

            if ($lockedRun->user_id !== $user->id) {
                throw new AuthorizationException;
            }

            if ($lockedRun->status !== ResearchRunStatus::Failed) {
                throw new DomainException('Only failed research runs may be retried.');
            }

            $query = $lockedRun->researchQuery()->lockForUpdate()->firstOrFail();
            $attemptNumber = ((int) $query->runs()->max('attempt_number')) + 1;
            $sourceCollection = $lockedRun->collectionRun()->firstOrFail();
            $collectionRun = $this->createCollectionRun->handle(
                user: $user,
                attemptNumber: $attemptNumber,
                requestedCount: $lockedRun->requested_result_count,
                frozenRequest: $sourceCollection->frozen_request,
                cachePolicy: $sourceCollection->cache_policy,
                freshnessWindowSeconds: (int) ($sourceCollection->configuration_context['freshness_window_seconds'] ?? 0),
            );

            return $query->runs()->create([
                'user_id' => $lockedRun->user_id,
                'collection_run_id' => $collectionRun->id,
                'kind' => $lockedRun->kind,
                'status' => ResearchRunStatus::Draft,
                'attempt_number' => $attemptNumber,
                'query_text' => $lockedRun->query_text,
                'market_key' => $lockedRun->market_key,
                'region_code' => $lockedRun->region_code,
                'relevance_language' => $lockedRun->relevance_language,
                'parameters' => $lockedRun->parameters,
                'requested_result_count' => $lockedRun->requested_result_count,
                'collected_result_count' => 0,
                'enriched_result_count' => 0,
                'progress_percent' => 0,
            ]);
        });

        return $this->queueRun->handle($user, $retry);
    }
}
