<?php

namespace App\Domain\Topics\ReadModels;

use App\Models\Market;
use App\Models\TopicWorkspace;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class BuildTopicWorkspaceIndex
{
    /**
     * @param  array{search:string,status:string,market:string}  $filters
     * @return array<string, mixed>
     */
    public function handle(User $user, array $filters): array
    {
        $query = $user->topicWorkspaces()->with(['market', 'project'])->withCount('items');
        if ($filters['search'] !== '') {
            $term = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filters['search']).'%';
            $query->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('description', 'like', $term));
        }
        if ($filters['status'] === 'active') {
            $query->whereNull('archived_at');
        }
        if ($filters['status'] === 'archived') {
            $query->whereNotNull('archived_at');
        }
        if ($filters['market'] !== 'all') {
            $query->where('market_key', $filters['market']);
        }

        $page = $query->latest('updated_at')->paginate(24)->withQueryString();

        return [
            'filters' => $filters,
            'workspaces' => $page->getCollection()->map(fn (TopicWorkspace $workspace) => [
                'public_id' => $workspace->public_id,
                'name' => $workspace->name,
                'description' => $workspace->description,
                'market_key' => $workspace->market_key,
                'language' => $workspace->relevance_language,
                'archived_at' => $workspace->archived_at?->toIso8601String(),
                'items_count' => $workspace->items_count,
                'project' => $workspace->project ? ['public_id' => $workspace->project->public_id, 'name' => $workspace->project->name] : null,
                'updated_at' => $workspace->updated_at?->toIso8601String(),
            ])->all(),
            'pagination' => $this->pagination($page),
            'markets' => Market::query()->where('is_enabled', true)->orderBy('sort_order')->get(['key', 'name', 'relevance_language']),
            'projects' => $user->researchProjects()->whereNull('archived_at')->orderBy('name')->get(['public_id', 'name']),
            'counts' => [
                'all' => $user->topicWorkspaces()->count(),
                'active' => $user->topicWorkspaces()->whereNull('archived_at')->count(),
                'archived' => $user->topicWorkspaces()->whereNotNull('archived_at')->count(),
            ],
        ];
    }

    /**
     * @param  LengthAwarePaginator<int, TopicWorkspace>  $page
     * @return array<string, int|null>
     */
    private function pagination(LengthAwarePaginator $page): array
    {
        return ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'from' => $page->firstItem(), 'to' => $page->lastItem(), 'total' => $page->total(), 'per_page' => $page->perPage()];
    }
}
