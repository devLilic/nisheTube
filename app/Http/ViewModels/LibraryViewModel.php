<?php

namespace App\Http\ViewModels;

use App\Domain\Library\Data\LibraryFilters;
use App\Domain\Library\Enums\LibraryTargetType;
use App\Domain\Library\ReadModels\LibraryQueries;
use App\Models\Channel;
use App\Models\Favorite;
use App\Models\Market;
use App\Models\NicheCandidate;
use App\Models\ResearchProject;
use App\Models\ResearchQuery;
use App\Models\ResearchRun;
use App\Models\Taggable;
use App\Models\User;
use App\Models\Video;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class LibraryViewModel
{
    private const PAGE_SIZE = 24;

    public function __construct(private readonly LibraryQueries $queries) {}

    /** @return array<string, mixed> */
    public function context(User $user, bool $includeFavorites = true): array
    {
        $favorites = $includeFavorites
            ? Favorite::query()
                ->where('user_id', $user->id)
                ->with(['project', 'target'])
                ->latest('updated_at')
                ->get()
            : collect();

        if ($includeFavorites) {
            $this->loadTags($favorites->all());
        }

        return [
            'projects' => ResearchProject::query()->where('user_id', $user->id)->whereNull('archived_at')->orderBy('name')->get()
                ->map(fn (ResearchProject $project): array => $this->projectOption($project))->values()->all(),
            'tags' => $this->tagOptions($user),
            'favorites' => $favorites->map(fn (Favorite $favorite): array => $this->favoriteSummary($favorite))->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    public function projectsIndex(User $user, Request $request): array
    {
        $archived = match ($request->string('status')->toString()) {
            'archived' => true,
            'all' => null,
            default => false,
        };
        $sort = $request->string('sort')->toString();
        $query = $this->queries->projects($user, new LibraryFilters(search: $request->string('search')->toString(), archived: $archived))
            ->withCount(['queries', 'favorites', 'discoveryRuns']);

        if ($sort === 'name') {
            $query->reorder('name');
        } elseif ($sort === 'oldest') {
            $query->reorder('created_at');
        }

        $projects = $query->paginate(self::PAGE_SIZE)->withQueryString();

        return [
            'projects' => $projects->getCollection()->map(fn (ResearchProject $project): array => $this->projectCard($project))->values()->all(),
            'pagination' => $this->pagination($projects),
            'filters' => [
                'search' => $request->string('search')->toString(),
                'status' => $request->string('status')->toString() ?: 'active',
                'sort' => $sort ?: 'updated',
                'view' => $request->string('view')->toString() === 'list' ? 'list' : 'grid',
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function projectDetail(User $user, ResearchProject $project): array
    {
        $project->load([
            'queries.market',
            'queries.runs' => fn ($query) => $query->latest()->limit(20),
            'discoveryRuns.market',
            'favorites.project',
            'favorites.target',
        ]);
        $this->loadTags($project->favorites->all());

        return [
            'project' => array_merge($this->projectCard($project), [
                'description' => $project->description,
                'purpose' => $project->purpose,
                'market_key' => $project->market_key,
                'themes' => $project->themes ?? [],
                'decision_status' => $project->decision_status,
                'decision_note' => $project->decision_note,
                'queries' => $project->queries->map(fn (ResearchQuery $query): array => [
                    'public_id' => $query->public_id,
                    'label' => $query->query_text,
                    'market' => $query->market->name,
                    'run_count' => $query->runs->count(),
                    'latest_run' => $query->runs->first() === null ? null : $this->runSummary($query->runs->first()),
                ])->values()->all(),
                'discovery_runs' => $project->discoveryRuns->sortByDesc('created_at')->map(fn ($run): array => [
                    'public_id' => $run->public_id,
                    'status' => $run->status->value,
                    'market' => $run->market->name,
                    'candidate_count' => $run->candidate_count,
                    'created_at' => $run->created_at?->toIso8601String(),
                ])->values()->all(),
                'favorites' => $project->favorites->map(fn (Favorite $favorite): array => $this->favoriteSummary($favorite))->values()->all(),
                'workspaces' => $project->topicWorkspaces()->orderByDesc('updated_at')->limit(20)->get()->map(fn ($workspace): array => [
                    'public_id' => $workspace->public_id,
                    'name' => $workspace->name,
                    'market_key' => $workspace->market_key,
                    'archived' => $workspace->archived_at !== null,
                    'updated_at' => $workspace->updated_at?->toIso8601String(),
                ])->values()->all(),
                'shortlist' => $project->favorites->filter(fn (Favorite $favorite): bool => $favorite->target_type === LibraryTargetType::ResearchRun->value)
                    ->map(fn (Favorite $favorite): array => ['public_id' => $favorite->public_id, 'label' => $favorite->target instanceof ResearchRun ? $favorite->target->query_text : 'Unavailable saved research run'])
                    ->values()->all(),
            ]),
            'library' => $this->context($user),
            'markets' => Market::query()->where('is_enabled', true)->orderBy('sort_order')->get()->map(fn (Market $market): array => ['key' => $market->key, 'name' => $market->name])->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    public function favoritesIndex(User $user, Request $request): array
    {
        $targetType = LibraryTargetType::tryFrom($request->string('type')->toString());
        $project = ResearchProject::query()->where('user_id', $user->id)->where('public_id', $request->string('project')->toString())->first();
        $tagId = $user->tags()->where('public_id', $request->string('tag')->toString())->value('id');
        $query = $this->queries->favorites($user, new LibraryFilters(
            targetType: $targetType,
            projectId: $project?->id,
            tagId: is_numeric($tagId) ? (int) $tagId : null,
            search: $request->string('search')->toString(),
        ));

        if ($request->string('sort')->toString() === 'oldest') {
            $query->reorder('created_at');
        }

        $favorites = $query->paginate(self::PAGE_SIZE)->withQueryString();
        $this->loadTags($favorites->items());

        return [
            'favorites' => $favorites->getCollection()->map(fn (Favorite $favorite): array => $this->favoriteSummary($favorite))->values()->all(),
            'pagination' => $this->pagination($favorites),
            'library' => $this->context($user, includeFavorites: false),
            'filters' => [
                'search' => $request->string('search')->toString(),
                'type' => $targetType === null ? 'all' : $targetType->value,
                'project' => $project === null ? 'all' : $project->public_id,
                'tag' => is_numeric($tagId) ? $request->string('tag')->toString() : 'all',
                'sort' => $request->string('sort')->toString() === 'oldest' ? 'oldest' : 'updated',
            ],
        ];
    }

    /**
     * @template TValue of Favorite|ResearchProject
     *
     * @param  LengthAwarePaginator<int, TValue>  $paginator
     * @return array{current_page: int, last_page: int, from: int|null, to: int|null, total: int, per_page: int}
     */
    private function pagination(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
        ];
    }

    /** @return array<string, mixed> */
    private function projectCard(ResearchProject $project): array
    {
        return [
            'public_id' => $project->public_id,
            'name' => $project->name,
            'description' => $project->description,
            'purpose' => $project->purpose,
            'market_key' => $project->market_key,
            'themes' => $project->themes ?? [],
            'decision_status' => $project->decision_status,
            'decision_note' => $project->decision_note,
            'color' => $project->color,
            'archived' => $project->archived_at !== null,
            'updated_at' => $project->updated_at?->toIso8601String(),
            'query_count' => (int) ($project->getAttribute('queries_count') ?? $project->queries()->count()),
            'favorite_count' => (int) ($project->getAttribute('favorites_count') ?? $project->favorites()->count()),
            'discovery_count' => (int) ($project->getAttribute('discovery_runs_count') ?? $project->discoveryRuns()->count()),
        ];
    }

    /** @return array{public_id: string, name: string, color: string|null} */
    private function projectOption(ResearchProject $project): array
    {
        return ['public_id' => $project->public_id, 'name' => $project->name, 'color' => $project->color];
    }

    /** @return array<string, mixed> */
    private function favoriteSummary(Favorite $favorite): array
    {
        $target = $favorite->target;
        $type = LibraryTargetType::from($favorite->target_type);

        return [
            'public_id' => $favorite->public_id,
            'target_type' => $type->value,
            'target_reference' => $target instanceof Model ? $this->targetReference($target) : '',
            'label' => $target instanceof Model ? $this->targetLabel($target) : 'Unavailable saved item',
            'subtitle' => $target instanceof Model ? $this->targetSubtitle($target) : 'The referenced item is unavailable.',
            'href' => $target instanceof Model ? $this->targetHref($target) : null,
            'note' => $favorite->note,
            'project' => $favorite->project === null ? null : $this->projectOption($favorite->project),
            'tags' => $target instanceof Model ? $this->targetTags($target) : [],
            'updated_at' => $favorite->updated_at?->toIso8601String(),
        ];
    }

    /** @return array<int, array{public_id: string, name: string, color: string|null}> */
    private function targetTags(Model $target): array
    {
        if (! $target->relationLoaded('taggables')) {
            return [];
        }

        $taggables = $target->getRelation('taggables');
        if (! $taggables instanceof Collection) {
            return [];
        }

        return $taggables
            ->filter(fn (mixed $taggable): bool => $taggable instanceof Taggable)
            ->map(fn (Taggable $taggable): array => [
                'public_id' => $taggable->tag->public_id,
                'name' => $taggable->tag->name,
                'color' => $taggable->tag->color,
            ])->values()->all();
    }

    /** @param array<int, Favorite> $favorites */
    private function loadTags(array $favorites): void
    {
        collect($favorites)->groupBy('target_type')->each(function ($group, string $type): void {
            $modelClass = LibraryTargetType::from($type)->modelClass();
            $models = $modelClass::query()->whereIn('id', $group->pluck('target_id'))->with('taggables.tag')->get()->keyBy('id');
            $group->each(fn (Favorite $favorite) => $favorite->setRelation('target', $models->get($favorite->target_id)));
        });
    }

    /** @return array<int, array{public_id: string, name: string, color: string|null}> */
    private function tagOptions(User $user): array
    {
        return $user->tags()->orderBy('name_key')->get()->map(fn ($tag): array => [
            'public_id' => $tag->public_id,
            'name' => $tag->name,
            'color' => $tag->color,
        ])->values()->all();
    }

    private function targetReference(Model $target): string
    {
        return match (true) {
            $target instanceof Video => $target->provider_video_id,
            $target instanceof Channel => $target->provider_channel_id,
            default => (string) $target->getAttribute('public_id'),
        };
    }

    private function targetLabel(Model $target): string
    {
        return match (true) {
            $target instanceof NicheCandidate => $target->phrase,
            $target instanceof Video, $target instanceof Channel => $target->title,
            $target instanceof ResearchQuery => $target->query_text,
            $target instanceof ResearchRun => $target->query_text,
            default => 'Saved item',
        };
    }

    private function targetSubtitle(Model $target): string
    {
        return match (true) {
            $target instanceof NicheCandidate => 'Discovery candidate',
            $target instanceof Video => 'YouTube video',
            $target instanceof Channel => 'YouTube channel',
            $target instanceof ResearchQuery => 'Saved research query',
            $target instanceof ResearchRun => 'Research run · '.$target->status->value,
            default => 'Library item',
        };
    }

    private function targetHref(Model $target): ?string
    {
        return match (true) {
            $target instanceof Video => 'https://www.youtube.com/watch?v='.rawurlencode($target->provider_video_id),
            $target instanceof Channel => 'https://www.youtube.com/channel/'.rawurlencode($target->provider_channel_id),
            $target instanceof ResearchRun => route('research.runs.show', $target, false),
            $target instanceof NicheCandidate => route('discovery.runs.show', $target->discoveryRun, false),
            default => null,
        };
    }

    /** @return array<string, mixed> */
    private function runSummary(ResearchRun $run): array
    {
        return [
            'public_id' => $run->public_id,
            'status' => $run->status->value,
            'completed_at' => $run->completed_at?->toIso8601String(),
        ];
    }
}
