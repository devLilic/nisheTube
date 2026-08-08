<?php

namespace App\Domain\Library\Actions;

use App\Domain\Library\Services\LibraryInputNormalizer;
use App\Domain\Library\Services\LibraryOwnership;
use App\Models\Tag;
use App\Models\User;
use DomainException;
use Illuminate\Support\Str;

class UpdateTag
{
    public function __construct(
        private readonly LibraryOwnership $ownership,
        private readonly LibraryInputNormalizer $normalize,
    ) {}

    public function handle(User $user, Tag $tag, string $name, ?string $color): Tag
    {
        $this->ownership->tag($user, $tag);
        $name = $this->normalize->name($name, 80);
        $nameKey = Str::lower($name);

        if (Tag::query()
            ->where('user_id', $user->id)
            ->where('name_key', $nameKey)
            ->whereKeyNot($tag->id)
            ->exists()) {
            throw new DomainException('A tag with this name already exists.');
        }

        $tag->update([
            'name' => $name,
            'name_key' => $nameKey,
            'color' => $this->normalize->color($color),
        ]);

        return $tag->fresh();
    }
}
