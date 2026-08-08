<?php

namespace App\Domain\Library\Actions;

use App\Domain\Library\Services\LibraryInputNormalizer;
use App\Domain\Library\Services\LibraryOwnership;
use App\Models\Favorite;
use App\Models\ResearchProject;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class UpdateFavorite
{
    public function __construct(
        private readonly LibraryOwnership $ownership,
        private readonly LibraryInputNormalizer $normalize,
    ) {}

    public function handle(
        User $user,
        Favorite $favorite,
        ?ResearchProject $project,
        ?string $note,
    ): Favorite {
        if ($favorite->user_id !== $user->id) {
            throw new AuthorizationException;
        }

        $this->ownership->project($user, $project);
        $favorite->update([
            'research_project_id' => $project?->id,
            'note' => $this->normalize->optionalText($note, 10000),
        ]);

        return $favorite->fresh();
    }
}
