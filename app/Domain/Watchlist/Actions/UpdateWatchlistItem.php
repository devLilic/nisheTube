<?php

namespace App\Domain\Watchlist\Actions;

use App\Domain\Library\Services\LibraryOwnership;
use App\Domain\Watchlist\Enums\WatchlistStatus;
use App\Models\ResearchProject;
use App\Models\TopicWorkspace;
use App\Models\User;
use App\Models\WatchlistItem;
use Illuminate\Auth\Access\AuthorizationException;

final readonly class UpdateWatchlistItem
{
    public function __construct(private LibraryOwnership $ownership) {}

    public function handle(User $user, WatchlistItem $item, WatchlistStatus $status, bool $active, ?ResearchProject $project, ?string $note, ?TopicWorkspace $workspace = null, ?bool $notifyOnRefresh = null): WatchlistItem
    {
        if ($item->user_id !== $user->id) {
            throw new AuthorizationException;
        }

        $this->ownership->project($user, $project);
        if ($workspace !== null && ($workspace->user_id !== $user->id || $workspace->archived_at !== null)) {
            throw new AuthorizationException;
        }
        $item->update([
            'status' => $status,
            'is_active' => $active,
            'notify_on_refresh' => $notifyOnRefresh ?? $item->notify_on_refresh,
            'research_project_id' => $project?->id,
            'topic_workspace_id' => $workspace?->id,
            'note' => $note,
            'next_refresh_at' => null,
        ]);

        return $item->fresh() ?? $item;
    }
}
