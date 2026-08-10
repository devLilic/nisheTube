<?php

namespace App\Domain\Library\Services;

use App\Domain\Library\Enums\LibraryTargetType;
use App\Models\Channel;
use App\Models\NicheCandidate;
use App\Models\ResearchProject;
use App\Models\ResearchQuery;
use App\Models\ResearchRun;
use App\Models\Tag;
use App\Models\User;
use App\Models\Video;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;

class LibraryOwnership
{
    /** @throws AuthorizationException */
    public function targetType(User $user, Model $target): LibraryTargetType
    {
        $type = LibraryTargetType::fromModel($target);
        $owned = match ($type) {
            LibraryTargetType::NicheCandidate => NicheCandidate::query()
                ->whereKey($target->getKey())
                ->whereHas('discoveryRun', fn ($query) => $query->where('user_id', $user->id))
                ->exists(),
            LibraryTargetType::ResearchQuery => ResearchQuery::query()
                ->whereKey($target->getKey())
                ->where('user_id', $user->id)
                ->exists(),
            LibraryTargetType::ResearchRun => ResearchRun::query()
                ->whereKey($target->getKey())
                ->where('user_id', $user->id)
                ->exists(),
            LibraryTargetType::Video => Video::query()
                ->whereKey($target->getKey())
                ->where(function ($query) use ($user): void {
                    $query->whereHas('researchRuns', fn ($research) => $research->where('research_runs.user_id', $user->id))
                        ->orWhereHas('analyzerRuns', fn ($analyzer) => $analyzer->where('analyzer_runs.user_id', $user->id));
                })
                ->exists(),
            LibraryTargetType::Channel => Channel::query()
                ->whereKey($target->getKey())
                ->where(function ($query) use ($user): void {
                    $query->whereHas('videos.researchRuns', fn ($research) => $research->where('research_runs.user_id', $user->id))
                        ->orWhereHas('analyzerRuns', fn ($analyzer) => $analyzer->where('analyzer_runs.user_id', $user->id));
                })
                ->exists(),
        };

        if (! $owned) {
            throw new AuthorizationException;
        }

        return $type;
    }

    /** @throws AuthorizationException */
    public function project(User $user, ?ResearchProject $project, bool $requireActive = true): void
    {
        if ($project === null) {
            return;
        }

        if ($project->user_id !== $user->id || ($requireActive && $project->archived_at !== null)) {
            throw new AuthorizationException;
        }
    }

    /** @throws AuthorizationException */
    public function tag(User $user, Tag $tag): void
    {
        if ($tag->user_id !== $user->id) {
            throw new AuthorizationException;
        }
    }
}
