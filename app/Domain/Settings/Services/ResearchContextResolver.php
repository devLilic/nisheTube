<?php

namespace App\Domain\Settings\Services;

use App\Models\Market;
use App\Models\ResearchProject;
use App\Models\TopicWorkspace;
use App\Models\User;

class ResearchContextResolver
{
    public const SESSION_KEY = 'research_context';

    private const OPTION_LIMIT = 100;

    /**
     * @return array{stored: array{user_id: int, market_key: string|null, project: string|null, workspace: string|null}, shared: array<string, mixed>}
     */
    public function resolve(User $user, mixed $stored): array
    {
        $stored = is_array($stored) ? $stored : [];
        $notices = [];
        if (($stored['user_id'] ?? null) !== $user->id) {
            $stored = [];
        }

        $markets = Market::query()
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['key', 'name']);
        $market = $markets->firstWhere('key', $stored['market_key'] ?? $user->default_market_key)
            ?? $markets->firstWhere('key', $user->default_market_key)
            ?? $markets->first();

        if (isset($stored['market_key']) && $market?->key !== $stored['market_key']) {
            $notices[] = 'The previous market is unavailable, so a safe default is active.';
        }

        $project = $this->activeProject($user, $stored['project'] ?? null);
        if (isset($stored['project']) && $project === null) {
            $notices[] = 'The previous project is archived or unavailable and was cleared.';
        }

        $workspace = $this->activeWorkspace($user, $stored['workspace'] ?? null);
        if (isset($stored['workspace']) && $workspace === null) {
            $notices[] = 'The previous workspace is archived or unavailable and was cleared.';
        }

        if ($workspace !== null && $market?->key !== $workspace->market_key) {
            $workspace = null;
            $notices[] = 'The previous workspace does not match the active market and was cleared.';
        }

        if (
            $workspace !== null
            && $workspace->research_project_id !== null
            && $workspace->research_project_id !== $project?->id
        ) {
            $workspace = null;
            $notices[] = 'The previous workspace does not belong to the active project and was cleared.';
        }

        $projects = ResearchProject::query()
            ->where('user_id', $user->id)
            ->whereNull('archived_at')
            ->orderBy('name')
            ->limit(self::OPTION_LIMIT)
            ->get(['public_id', 'name']);
        $workspaces = TopicWorkspace::query()
            ->where('user_id', $user->id)
            ->whereNull('archived_at')
            ->with('project')
            ->orderBy('name_key')
            ->limit(self::OPTION_LIMIT)
            ->get(['public_id', 'name', 'market_key', 'research_project_id']);

        $normalized = [
            'user_id' => $user->id,
            'market_key' => $market?->key,
            'project' => $project?->public_id,
            'workspace' => $workspace?->public_id,
        ];

        return [
            'stored' => $normalized,
            'shared' => [
                'selection' => [
                    'market' => $market === null ? null : ['value' => $market->key, 'label' => $market->name],
                    'project' => $project === null ? null : ['value' => $project->public_id, 'label' => $project->name],
                    'workspace' => $workspace === null ? null : [
                        'value' => $workspace->public_id,
                        'label' => $workspace->name,
                        'marketKey' => $workspace->market_key,
                        'project' => $workspace->project?->public_id,
                    ],
                ],
                'options' => [
                    'markets' => $markets->map(fn (Market $item): array => ['value' => $item->key, 'label' => $item->name])->values()->all(),
                    'projects' => $projects->map(fn (ResearchProject $item): array => ['value' => $item->public_id, 'label' => $item->name])->values()->all(),
                    'workspaces' => $workspaces->map(fn (TopicWorkspace $item): array => [
                        'value' => $item->public_id,
                        'label' => $item->name,
                        'marketKey' => $item->market_key,
                        'project' => $item->project?->public_id,
                    ])->values()->all(),
                ],
                'notice' => $notices === [] ? null : implode(' ', $notices),
            ],
        ];
    }

    private function activeProject(User $user, mixed $reference): ?ResearchProject
    {
        if (! is_string($reference) || $reference === '') {
            return null;
        }

        return ResearchProject::query()
            ->where('user_id', $user->id)
            ->whereNull('archived_at')
            ->where('public_id', $reference)
            ->first();
    }

    private function activeWorkspace(User $user, mixed $reference): ?TopicWorkspace
    {
        if (! is_string($reference) || $reference === '') {
            return null;
        }

        return TopicWorkspace::query()
            ->where('user_id', $user->id)
            ->whereNull('archived_at')
            ->where('public_id', $reference)
            ->with('project')
            ->first();
    }
}
