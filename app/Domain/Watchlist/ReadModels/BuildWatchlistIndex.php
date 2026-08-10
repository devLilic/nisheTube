<?php

namespace App\Domain\Watchlist\ReadModels;

use App\Models\Channel;
use App\Models\ResearchProject;
use App\Models\Tag;
use App\Models\User;
use App\Models\Video;
use App\Models\WatchlistItem;
use App\Models\WatchlistRefreshRun;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class BuildWatchlistIndex
{
    private const PER_PAGE = 24;

    /**
     * @param  array{search: string, type: string, status: string, activity: string, project: string}  $filters
     * @return array<string, mixed>
     */
    public function handle(User $user, array $filters): array
    {
        $query = WatchlistItem::query()
            ->where('user_id', $user->id)
            ->with(['target', 'project', 'workspace', 'lastRefreshRun.analyzerRun']);

        if (in_array($filters['type'], ['video', 'channel'], true)) {
            $query->where('target_type', $filters['type']);
        }
        if ($filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }
        if ($filters['activity'] === 'active') {
            $query->where('is_active', true);
        } elseif ($filters['activity'] === 'paused') {
            $query->where('is_active', false);
        }
        if ($filters['project'] !== 'all') {
            $query->whereHas('project', fn ($projects) => $projects->where('user_id', $user->id)->where('public_id', $filters['project']));
        }
        if ($filters['search'] !== '') {
            $term = '%'.$this->escapeLike($filters['search']).'%';
            $query->where(function ($items) use ($term): void {
                $items->where('note', 'like', $term)
                    ->orWhereHasMorph('target', [Video::class, Channel::class], fn ($targets) => $targets->where('title', 'like', $term));
            });
        }

        $page = $query->latest('updated_at')->paginate(self::PER_PAGE)->withQueryString();

        return [
            'filters' => $filters,
            'items' => $page->getCollection()->map(fn (WatchlistItem $item): array => $this->item($item, $user))->values()->all(),
            'pagination' => $this->pagination($page),
            'projects' => $user->researchProjects()->whereNull('archived_at')->orderBy('name')->get()->map(fn ($project): array => [
                'public_id' => $project->public_id,
                'name' => $project->name,
                'color' => $project->color,
            ])->all(),
            'tags' => $user->tags()->orderBy('name_key')->get()->map(fn ($tag): array => [
                'public_id' => $tag->public_id,
                'name' => $tag->name,
                'color' => $tag->color,
            ])->all(),
            'workspaces' => $user->topicWorkspaces()->whereNull('archived_at')->orderBy('name')->get()->map(fn ($workspace): array => [
                'public_id' => $workspace->public_id, 'name' => $workspace->name, 'market_key' => $workspace->market_key,
            ])->all(),
            'counts' => [
                'all' => $user->watchlistItems()->count(),
                'active' => $user->watchlistItems()->where('is_active', true)->count(),
                'video' => $user->watchlistItems()->where('target_type', 'video')->count(),
                'channel' => $user->watchlistItems()->where('target_type', 'channel')->count(),
            ],
            'workspace_available' => true,
        ];
    }

    /** @return array<string, mixed> */
    private function item(WatchlistItem $item, User $user): array
    {
        $target = $item->target;
        $refresh = $item->last_refresh_run_id === null ? null : WatchlistRefreshRun::query()->with('analyzerRun')->find($item->last_refresh_run_id);
        $activeRefresh = WatchlistRefreshRun::query()->with('analyzerRun')->where('watchlist_item_id', $item->id)->whereIn('status', ['queued', 'processing'])->latest('id')->first();
        $displayRefresh = $activeRefresh ?? $refresh;
        $favorite = $target === null ? null : $user->favorites()->where('target_type', $item->target_type)->where('target_id', $target->getKey())->first();
        $tags = ($target instanceof Video || $target instanceof Channel)
            ? Tag::query()->where('user_id', $user->id)->whereHas('taggables', fn ($links) => $links
                ->where('target_type', $item->target_type)->where('target_id', $target->getKey()))->get()
            : collect();
        $project = $item->research_project_id === null ? null : ResearchProject::query()->where('user_id', $user->id)->find($item->research_project_id);

        return [
            'public_id' => $item->public_id,
            'target_type' => $item->target_type,
            'target_reference' => $target instanceof Video ? $target->provider_video_id : ($target instanceof Channel ? $target->provider_channel_id : null),
            'label' => $target?->getAttribute('title') ?? 'Unavailable watched subject',
            'thumbnail_url' => $target?->getAttribute('thumbnail_url'),
            'youtube_url' => $target instanceof Video
                ? 'https://www.youtube.com/watch?v='.$target->provider_video_id
                : ($target instanceof Channel ? 'https://www.youtube.com/channel/'.$target->provider_channel_id : null),
            'analyzer_url' => $displayRefresh?->analyzerRun === null ? null : '/analyzer/runs/'.$displayRefresh->analyzerRun->public_id.'?return_to='.rawurlencode('/watchlist'),
            'status' => $item->status->value,
            'is_active' => $item->is_active,
            'refresh_mode' => $item->refresh_mode,
            'note' => $item->note,
            'project' => $project === null ? null : ['public_id' => $project->public_id, 'name' => $project->name],
            'workspace' => $item->workspace === null ? null : ['public_id' => $item->workspace->public_id, 'name' => $item->workspace->name],
            'favorite' => $favorite !== null,
            'tags' => $tags->map(fn ($tag): array => ['public_id' => $tag->public_id, 'name' => $tag->name, 'color' => $tag->color])->values()->all(),
            'last_observed_at' => $item->last_observed_at?->toIso8601String(),
            'last_refreshed_at' => $item->last_refreshed_at?->toIso8601String(),
            'next_refresh_at' => null,
            'refresh' => $displayRefresh === null ? null : [
                'public_id' => $displayRefresh->public_id,
                'status' => $displayRefresh->status->value,
                'progress_percent' => $activeRefresh === null
                    ? $displayRefresh->progress_percent
                    : $activeRefresh->analyzerRun->progress_percent,
                'warnings' => $displayRefresh->warnings ?? [],
                'error_code' => $displayRefresh->error_code,
                'error_message' => $displayRefresh->error_message,
                'deltas' => $displayRefresh->deltas,
                'completed_at' => $displayRefresh->completed_at?->toIso8601String(),
                'is_active' => $activeRefresh !== null,
                'quota_exhausted' => in_array($displayRefresh->error_code, ['youtube_quota_exhausted', 'quota_exhausted'], true),
            ],
        ];
    }

    /**
     * @param  LengthAwarePaginator<int, WatchlistItem>  $page
     * @return array<string, int|null>
     */
    private function pagination(LengthAwarePaginator $page): array
    {
        return [
            'current_page' => $page->currentPage(), 'last_page' => $page->lastPage(),
            'from' => $page->firstItem(), 'to' => $page->lastItem(), 'total' => $page->total(), 'per_page' => $page->perPage(),
        ];
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
