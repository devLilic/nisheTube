<?php

namespace App\Domain\Library\Actions;

use App\Domain\Library\Services\LibraryOwnership;
use App\Models\Tag;
use App\Models\User;

class DeleteTag
{
    public function __construct(private readonly LibraryOwnership $ownership) {}

    public function handle(User $user, Tag $tag): void
    {
        $this->ownership->tag($user, $tag);
        $tag->delete();
    }
}
