<?php

namespace App\Domain\Library\Actions;

use App\Domain\Library\Services\LibraryInputNormalizer;
use App\Domain\Library\Services\LibraryOwnership;
use App\Models\Favorite;
use App\Models\ResearchProject;
use App\Models\User;
use DomainException;
use Illuminate\Database\Eloquent\Model;

class CreateFavorite
{
    public function __construct(
        private readonly LibraryOwnership $ownership,
        private readonly LibraryInputNormalizer $normalize,
    ) {}

    public function handle(
        User $user,
        Model $target,
        ?ResearchProject $project = null,
        ?string $note = null,
    ): Favorite {
        $type = $this->ownership->targetType($user, $target);
        $this->ownership->project($user, $project);
        $favorite = Favorite::query()->firstOrCreate(
            [
                'user_id' => $user->id,
                'target_type' => $type->value,
                'target_id' => $target->getKey(),
            ],
            [
                'research_project_id' => $project?->id,
                'note' => $this->normalize->optionalText($note, 10000),
            ],
        );

        if (! $favorite->wasRecentlyCreated) {
            throw new DomainException('This item is already saved to favorites.');
        }

        return $favorite;
    }
}
