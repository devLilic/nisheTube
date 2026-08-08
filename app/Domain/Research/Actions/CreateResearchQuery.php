<?php

namespace App\Domain\Research\Actions;

use App\Domain\Research\Enums\SearchOrder;
use App\Domain\Research\Enums\VideoDurationFilter;
use App\Domain\Settings\Actions\FreezeMarketForRequest;
use App\Models\Market;
use App\Models\ResearchProject;
use App\Models\ResearchQuery;
use App\Models\User;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;

class CreateResearchQuery
{
    public function __construct(
        private readonly FreezeMarketForRequest $freezeMarket,
    ) {}

    /**
     * @throws AuthorizationException
     */
    public function handle(
        User $user,
        Market $market,
        string $queryText,
        ?ResearchProject $project = null,
        SearchOrder $searchOrder = SearchOrder::Relevance,
        ?CarbonInterface $publishedAfter = null,
        ?CarbonInterface $publishedBefore = null,
        ?VideoDurationFilter $videoDuration = null,
        ?string $videoCategoryId = null,
    ): ResearchQuery {
        ($this->freezeMarket)->handle($market);

        if ($project !== null && $project->user_id !== $user->id) {
            throw new AuthorizationException;
        }

        if ($publishedAfter !== null && $publishedBefore !== null && $publishedAfter->greaterThan($publishedBefore)) {
            throw new DomainException('The published-after boundary must be before the published-before boundary.');
        }

        $videoCategoryId = $this->normalizeCategoryId($videoCategoryId);

        return ResearchQuery::query()->create([
            'user_id' => $user->id,
            'research_project_id' => $project?->id,
            'market_id' => $market->id,
            'query_text' => $queryText,
            'search_order' => $searchOrder,
            'published_after' => $publishedAfter?->copy()->utc(),
            'published_before' => $publishedBefore?->copy()->utc(),
            'video_duration' => $videoDuration,
            'video_category_id' => $videoCategoryId,
        ]);
    }

    private function normalizeCategoryId(?string $categoryId): ?string
    {
        if ($categoryId === null || trim($categoryId) === '') {
            return null;
        }

        $categoryId = trim($categoryId);

        if (preg_match('/^[0-9]{1,32}$/', $categoryId) !== 1) {
            throw new DomainException('The video category identifier is invalid.');
        }

        return $categoryId;
    }
}
