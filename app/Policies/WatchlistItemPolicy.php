<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WatchlistItem;

class WatchlistItemPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function view(User $user, WatchlistItem $item): bool
    {
        return $item->user_id === $user->id;
    }

    public function update(User $user, WatchlistItem $item): bool
    {
        return $item->user_id === $user->id;
    }

    public function refresh(User $user, WatchlistItem $item): bool
    {
        return $item->user_id === $user->id && $item->is_active;
    }

    public function delete(User $user, WatchlistItem $item): bool
    {
        return $item->user_id === $user->id;
    }
}
