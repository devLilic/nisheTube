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
        ?string $purpose = null,
        ?string $marketKey = null,
        ?string $themes = null,
        ?string $decisionStatus = null,
        ?string $decisionNote = null,
    ): ResearchProject {
        $this->ownership->project($user, $project, false);
        $project->update([
            'name' => $this->normalize->name($name),
            'description' => $this->normalize->optionalText($description, 10000),
            'color' => $this->normalize->color($color),
            'purpose' => $this->normalize->optionalText($purpose, 2000),
            'market_key' => $this->normalize->optionalText($marketKey, 32),
            'themes' => $this->themes($themes),
            'decision_status' => $decisionStatus ?? $project->decision_status,
            'decision_note' => $this->normalize->optionalText($decisionNote, 4000),
        ]);

        return $project->fresh();
    }

    /** @return list<string>|null */
    private function themes(?string $value): ?array
    {
        $themes = collect(preg_split('/[,\n]+/', $value ?? '') ?: [])
            ->map(fn (string $theme): string => trim($theme))
            ->filter()
            ->map(fn (string $theme): string => $this->normalize->name($theme, 80))
            ->unique(fn (string $theme): string => mb_strtolower($theme))
            ->take(12)
            ->values()
            ->all();

        return $themes === [] ? null : array_values($themes);
    }
}
