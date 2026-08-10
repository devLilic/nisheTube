<?php

namespace App\Domain\Topics\Actions;

use App\Domain\Settings\Actions\FreezeMarketForRequest;
use App\Models\Market;
use App\Models\ResearchProject;
use App\Models\TopicWorkspace;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;

final class CreateTopicWorkspace
{
    public function __construct(private readonly FreezeMarketForRequest $freezeMarket) {}

    public function handle(User $user, Market $market, string $name, ?string $description, ?ResearchProject $project): TopicWorkspace
    {
        if ($project !== null && ($project->user_id !== $user->id || $project->archived_at !== null)) {
            throw new AuthorizationException;
        }

        $name = Str::squish($name);
        $frozen = $this->freezeMarket->handle($market);

        return $user->topicWorkspaces()->create([
            'research_project_id' => $project?->id,
            'market_id' => $market->id,
            'name' => $name,
            'name_key' => Str::lower($name),
            'description' => $description,
            'market_key' => $frozen->marketKey,
            'region_code' => $frozen->regionCode,
            'relevance_language' => $frozen->relevanceLanguage,
        ]);
    }
}
