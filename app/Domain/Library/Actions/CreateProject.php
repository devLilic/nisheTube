<?php

namespace App\Domain\Library\Actions;

use App\Domain\Library\Services\LibraryInputNormalizer;
use App\Models\ResearchProject;
use App\Models\User;

class CreateProject
{
    public function __construct(private readonly LibraryInputNormalizer $normalize) {}

    public function handle(User $user, string $name, ?string $description = null, ?string $color = null): ResearchProject
    {
        return ResearchProject::query()->create([
            'user_id' => $user->id,
            'name' => $this->normalize->name($name),
            'description' => $this->normalize->optionalText($description, 10000),
            'color' => $this->normalize->color($color),
        ]);
    }
}
