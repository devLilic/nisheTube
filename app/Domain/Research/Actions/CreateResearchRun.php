<?php

namespace App\Domain\Research\Actions;

use App\Domain\Collection\Actions\CreateResearchCollectionRun;
use App\Domain\Collection\Enums\CollectionCachePolicy;
use App\Domain\Research\Enums\ResearchRunKind;
use App\Domain\Research\Enums\ResearchRunStatus;
use App\Domain\Settings\Actions\FreezeMarketForRequest;
use App\Models\ResearchQuery;
use App\Models\ResearchRun;
use App\Models\User;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class CreateResearchRun
{
    public function __construct(
        private readonly FreezeMarketForRequest $freezeMarket,
        private readonly CreateResearchCollectionRun $createCollectionRun,
    ) {}

    /**
     * @throws AuthorizationException
     */
    public function handle(
        User $user,
        ResearchQuery $query,
        int $requestedResultCount,
        ResearchRunKind $kind = ResearchRunKind::Search,
        CollectionCachePolicy $cachePolicy = CollectionCachePolicy::FreshOnly,
        ?int $freshnessWindowSeconds = null,
    ): ResearchRun {
        if ($requestedResultCount < 1 || $requestedResultCount > 500) {
            throw new DomainException('The requested result count must be between 1 and 500.');
        }

        return DB::transaction(function () use (
            $user,
            $query,
            $requestedResultCount,
            $kind,
            $cachePolicy,
            $freshnessWindowSeconds,
        ): ResearchRun {
            $lockedQuery = ResearchQuery::query()
                ->with('market')
                ->lockForUpdate()
                ->findOrFail($query->id);

            if ($lockedQuery->user_id !== $user->id) {
                throw new AuthorizationException;
            }

            $market = $lockedQuery->market;
            $frozenMarket = ($this->freezeMarket)->handle($market);
            $attemptNumber = ((int) $lockedQuery->runs()->max('attempt_number')) + 1;
            $parameters = $this->frozenParameters($lockedQuery);
            $collectionRun = $this->createCollectionRun->handle(
                user: $user,
                attemptNumber: $attemptNumber,
                requestedCount: $requestedResultCount,
                frozenRequest: [
                    'query_text' => $lockedQuery->query_text,
                    'market_key' => $frozenMarket->marketKey,
                    'region_code' => $frozenMarket->regionCode,
                    'relevance_language' => $frozenMarket->relevanceLanguage,
                    'parameters' => $parameters,
                ],
                cachePolicy: $cachePolicy,
                freshnessWindowSeconds: $freshnessWindowSeconds,
            );

            return $lockedQuery->runs()->create([
                'user_id' => $user->id,
                'collection_run_id' => $collectionRun->id,
                'kind' => $kind,
                'status' => ResearchRunStatus::Draft,
                'attempt_number' => $attemptNumber,
                'query_text' => $lockedQuery->query_text,
                'market_key' => $frozenMarket->marketKey,
                'region_code' => $frozenMarket->regionCode,
                'relevance_language' => $frozenMarket->relevanceLanguage,
                'parameters' => $parameters,
                'requested_result_count' => $requestedResultCount,
                'collected_result_count' => 0,
                'enriched_result_count' => 0,
                'progress_percent' => 0,
            ]);
        });
    }

    /** @return array{search_order: string, published_after: string|null, published_before: string|null, video_duration: string|null, video_category_id: string|null} */
    private function frozenParameters(ResearchQuery $query): array
    {
        return [
            'search_order' => $query->search_order->value,
            'published_after' => $query->published_after?->utc()->toIso8601String(),
            'published_before' => $query->published_before?->utc()->toIso8601String(),
            'video_duration' => $query->video_duration?->value,
            'video_category_id' => $query->video_category_id,
        ];
    }
}
