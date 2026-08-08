<?php

namespace App\Domain\Library\Actions;

use App\Models\Favorite;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class DeleteFavorite
{
    public function handle(User $user, Favorite $favorite): void
    {
        if ($favorite->user_id !== $user->id) {
            throw new AuthorizationException;
        }

        DB::transaction(function () use ($user, $favorite): void {
            DB::table('taggables')
                ->whereIn('tag_id', $user->tags()->select('id'))
                ->where('target_type', $favorite->target_type)
                ->where('target_id', $favorite->target_id)
                ->delete();
            $favorite->delete();
        });
    }
}
