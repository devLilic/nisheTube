<?php

namespace App\Policies;

use App\Models\ResearchExport;
use App\Models\User;

class ResearchExportPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function view(User $user, ResearchExport $export): bool
    {
        return $export->user_id === $user->id;
    }

    public function download(User $user, ResearchExport $export): bool
    {
        return $this->view($user, $export);
    }

    public function delete(User $user, ResearchExport $export): bool
    {
        return $this->view($user, $export);
    }

    public function retry(User $user, ResearchExport $export): bool
    {
        return $this->view($user, $export);
    }
}
