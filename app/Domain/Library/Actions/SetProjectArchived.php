<?php

namespace App\Domain\Library\Actions;

use App\Domain\Library\Services\LibraryOwnership;
use App\Models\ResearchProject;
use App\Models\User;
use Illuminate\Support\Facades\Date;

class SetProjectArchived
{
    public function __construct(private readonly LibraryOwnership $ownership) {}

    public function handle(User $user, ResearchProject $project, bool $archived): ResearchProject
    {
        $this->ownership->project($user, $project, false);
        $project->update([
            'archived_at' => $archived ? ($project->archived_at ?? Date::now()) : null,
        ]);

        return $project->fresh();
    }
}
