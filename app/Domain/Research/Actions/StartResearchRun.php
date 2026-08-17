<?php

namespace App\Domain\Research\Actions;

use App\Domain\Research\Enums\PublishedWindow;
use App\Domain\Research\Enums\SearchOrder;
use App\Domain\Research\Enums\VideoDurationFilter;
use App\Models\Market;
use App\Models\ResearchRun;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class StartResearchRun
{
    public function __construct(
        private readonly ResolvePublishedWindow $resolvePublishedWindow,
        private readonly CreateResearchQuery $createResearchQuery,
        private readonly CreateResearchRun $createResearchRun,
        private readonly QueueResearchRun $queueResearchRun,
    ) {}

    /** @param array<string, string> $intakeContext */
    public function handle(
        User $user,
        Market $market,
        string $queryText,
        int $requestedResultCount,
        SearchOrder $searchOrder,
        PublishedWindow $publishedWindow,
        ?string $publishedAfter,
        ?string $publishedBefore,
        VideoDurationFilter $videoDuration,
        ?string $videoCategoryId,
        ?string $submissionToken = null,
        array $intakeContext = [],
    ): ResearchRun {
        return DB::transaction(function () use (
            $user,
            $market,
            $queryText,
            $requestedResultCount,
            $searchOrder,
            $publishedWindow,
            $publishedAfter,
            $publishedBefore,
            $videoDuration,
            $videoCategoryId,
            $submissionToken,
            $intakeContext,
        ): ResearchRun {
            [$resolvedAfter, $resolvedBefore] = $this->resolvePublishedWindow->handle(
                $publishedWindow,
                $user->timezone,
                $publishedAfter,
                $publishedBefore,
            );

            $query = $this->createResearchQuery->handle(
                user: $user,
                market: $market,
                queryText: $queryText,
                searchOrder: $searchOrder,
                publishedAfter: $resolvedAfter,
                publishedBefore: $resolvedBefore,
                videoDuration: $videoDuration,
                videoCategoryId: $videoCategoryId,
            );

            $run = $this->createResearchRun->handle(
                $user,
                $query,
                $requestedResultCount,
                submissionToken: $submissionToken,
                intakeContext: $intakeContext,
            );

            return $this->queueResearchRun->handle($user, $run);
        });
    }
}
