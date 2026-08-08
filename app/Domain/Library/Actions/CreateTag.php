<?php

namespace App\Domain\Library\Actions;

use App\Domain\Library\Services\LibraryInputNormalizer;
use App\Models\Tag;
use App\Models\User;
use DomainException;
use Illuminate\Support\Str;

class CreateTag
{
    public function __construct(private readonly LibraryInputNormalizer $normalize) {}

    public function handle(User $user, string $name, ?string $color = null): Tag
    {
        $name = $this->normalize->name($name, 80);
        $tag = Tag::query()->firstOrCreate(
            ['user_id' => $user->id, 'name_key' => Str::lower($name)],
            ['name' => $name, 'color' => $this->normalize->color($color)],
        );

        if (! $tag->wasRecentlyCreated) {
            throw new DomainException('A tag with this name already exists.');
        }

        return $tag;
    }
}
