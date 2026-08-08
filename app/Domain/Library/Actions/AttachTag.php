<?php

namespace App\Domain\Library\Actions;

use App\Domain\Library\Services\LibraryOwnership;
use App\Models\Favorite;
use App\Models\Tag;
use App\Models\Taggable;
use App\Models\User;
use DomainException;
use Illuminate\Database\Eloquent\Model;

class AttachTag
{
    public function __construct(private readonly LibraryOwnership $ownership) {}

    public function handle(User $user, Tag $tag, Model $target): Taggable
    {
        $this->ownership->tag($user, $tag);
        $type = $this->ownership->targetType($user, $target);

        if (! Favorite::query()
            ->where('user_id', $user->id)
            ->where('target_type', $type->value)
            ->where('target_id', $target->getKey())
            ->exists()) {
            throw new DomainException('Only saved library items can be tagged.');
        }

        return Taggable::query()->firstOrCreate([
            'tag_id' => $tag->id,
            'target_type' => $type->value,
            'target_id' => $target->getKey(),
        ]);
    }
}
