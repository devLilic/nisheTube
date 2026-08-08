<?php

namespace App\Domain\Library\ReadModels;

use App\Domain\Library\Data\LibraryFilters;
use App\Models\Favorite;
use App\Models\ResearchProject;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class LibraryQueries
{
    /** @return Builder<ResearchProject> */
    public function projects(User $user, LibraryFilters $filters): Builder
    {
        return ResearchProject::query()
            ->where('user_id', $user->id)
            ->when($filters->archived === true, fn (Builder $query) => $query->whereNotNull('archived_at'))
            ->when($filters->archived === false, fn (Builder $query) => $query->whereNull('archived_at'))
            ->when($this->search($filters) !== null, function (Builder $query) use ($filters): void {
                $search = $this->search($filters);
                $query->where(fn (Builder $nested) => $nested
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%"));
            })
            ->orderByDesc('updated_at');
    }

    /** @return Builder<Favorite> */
    public function favorites(User $user, LibraryFilters $filters): Builder
    {
        return Favorite::query()
            ->where('user_id', $user->id)
            ->when($filters->targetType !== null, fn (Builder $query) => $query->where('target_type', $filters->targetType?->value))
            ->when($filters->projectId !== null, fn (Builder $query) => $query->where('research_project_id', $filters->projectId))
            ->when($filters->tagId !== null, fn (Builder $query) => $query->whereExists(function ($taggables) use ($filters): void {
                $taggables->selectRaw('1')
                    ->from('taggables')
                    ->whereColumn('taggables.target_type', 'favorites.target_type')
                    ->whereColumn('taggables.target_id', 'favorites.target_id')
                    ->where('taggables.tag_id', $filters->tagId);
            }))
            ->when($this->search($filters) !== null, fn (Builder $query) => $query->where('note', 'like', '%'.$this->search($filters).'%'))
            ->with(['project', 'target'])
            ->orderByDesc('updated_at');
    }

    /** @return Builder<Tag> */
    public function tags(User $user, LibraryFilters $filters): Builder
    {
        return Tag::query()
            ->where('user_id', $user->id)
            ->when($this->search($filters) !== null, fn (Builder $query) => $query->where('name', 'like', '%'.$this->search($filters).'%'))
            ->withCount('taggables')
            ->orderBy('name_key');
    }

    private function search(LibraryFilters $filters): ?string
    {
        $search = $filters->search === null ? '' : Str::squish($filters->search);

        return $search === '' ? null : $search;
    }
}
