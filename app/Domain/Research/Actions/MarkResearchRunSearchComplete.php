<?php

namespace App\Domain\Research\Actions;

use App\Domain\Research\Enums\ResearchRunStatus;
use App\Models\ResearchRun;
use Illuminate\Support\Facades\DB;

class MarkResearchRunSearchComplete
{
    public function handle(ResearchRun $run, ?string $warning = null): ResearchRun
    {
        return DB::transaction(function () use ($run, $warning): ResearchRun {
            $lockedRun = ResearchRun::query()->lockForUpdate()->findOrFail($run->id);

            if ($lockedRun->status !== ResearchRunStatus::Searching) {
                return $lockedRun;
            }

            $warnings = $lockedRun->collection_warnings ?? [];

            if ($warning !== null) {
                $warnings[] = $warning;
            }

            $lockedRun->update([
                'collected_result_count' => $lockedRun->searchResults()->count(),
                'progress_percent' => 50,
                'collection_warnings' => $warnings === []
                    ? null
                    : array_values(array_unique($warnings)),
            ]);

            return $lockedRun;
        });
    }
}
