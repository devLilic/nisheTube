<?php

namespace App\Domain\Topics\Actions;

use App\Models\ResearchProject;
use App\Models\TopicWorkspace;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;

final class UpdateTopicWorkspace
{
    public function handle(User $user, TopicWorkspace $workspace, string $name, ?string $description, ?ResearchProject $project): TopicWorkspace
    {
        if ($workspace->user_id !== $user->id || ($project !== null && ($project->user_id !== $user->id || $project->archived_at !== null))) {
            throw new AuthorizationException;
        }

        $name = Str::squish($name);
        $workspace->update([
            'name' => $name,
            'name_key' => Str::lower($name),
            'description' => $description,
            'research_project_id' => $project?->id,
        ]);

        return $workspace;
    }
}
