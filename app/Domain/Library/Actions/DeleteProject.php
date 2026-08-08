<?php

namespace App\Domain\Library\Actions;

use App\Domain\Library\Services\LibraryOwnership;
use App\Models\ResearchProject;
use App\Models\User;

class DeleteProject
{
    public function __construct(private readonly LibraryOwnership $ownership) {}

    public function handle(User $user, ResearchProject $project): void
    {
        $this->ownership->project($user, $project, false);
        $project->delete();
    }
}
