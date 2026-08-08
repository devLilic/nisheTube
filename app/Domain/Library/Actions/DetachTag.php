<?php

namespace App\Domain\Library\Actions;

use App\Domain\Library\Services\LibraryOwnership;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class DetachTag
{
    public function __construct(private readonly LibraryOwnership $ownership) {}

    public function handle(User $user, Tag $tag, Model $target): void
    {
        $this->ownership->tag($user, $tag);
        $type = $this->ownership->targetType($user, $target);
        $tag->taggables()
            ->where('target_type', $type->value)
            ->where('target_id', $target->getKey())
            ->delete();
    }
}
