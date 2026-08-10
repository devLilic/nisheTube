<?php

namespace App\Domain\Watchlist\Actions;

use App\Domain\Library\Enums\LibraryTargetType;
use App\Domain\Library\Services\LibraryOwnership;
use App\Domain\Library\Services\ResolveLibraryTarget;
use App\Models\ResearchProject;
use App\Models\User;
use App\Models\WatchlistItem;
use Illuminate\Support\Facades\DB;

final readonly class CreateWatchlistItem
{
    public function __construct(
        private ResolveLibraryTarget $targets,
        private LibraryOwnership $ownership,
    ) {}

    public function handle(User $user, LibraryTargetType $type, string $reference, ?ResearchProject $project = null, ?string $note = null): WatchlistItem
    {
        if (! in_array($type, [LibraryTargetType::Video, LibraryTargetType::Channel], true)) {
            throw new \DomainException('Only videos and channels can be watched in this release.');
        }

        $target = $this->targets->handle($user, $type, $reference);
        $this->ownership->project($user, $project);

        return DB::transaction(fn (): WatchlistItem => WatchlistItem::query()->firstOrCreate([
            'user_id' => $user->id,
            'target_type' => $type->value,
            'target_id' => (int) $target->getKey(),
        ], [
            'research_project_id' => $project?->id,
            'status' => 'monitoring',
            'is_active' => true,
            'refresh_mode' => 'manual',
            'note' => $note,
        ]));
    }
}
