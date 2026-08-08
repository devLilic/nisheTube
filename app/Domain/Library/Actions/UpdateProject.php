<?php

namespace App\Domain\Library\Actions;

use App\Domain\Library\Services\LibraryInputNormalizer;
use App\Domain\Library\Services\LibraryOwnership;
use App\Models\ResearchProject;
use App\Models\User;

class UpdateProject
{
    public function __construct(
        private readonly LibraryOwnership $ownership,
        private readonly LibraryInputNormalizer $normalize,
    ) {}

    public function handle(
        User $user,
        ResearchProject $project,
        string $name,
        ?string $description,
        ?string $color,
    ): ResearchProject {
        $this->ownership->project($user, $project, false);
        $project->update([
            'name' => $this->normalize->name($name),
            'description' => $this->normalize->optionalText($description, 10000),
            'color' => $this->normalize->color($color),
        ]);

        return $project->fresh();
    }
}
