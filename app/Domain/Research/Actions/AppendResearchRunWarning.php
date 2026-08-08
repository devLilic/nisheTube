<?php

namespace App\Domain\Research\Actions;

use App\Models\ResearchRun;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AppendResearchRunWarning
{
    public function handle(ResearchRun $run, string $warning): ResearchRun
    {
        $warning = trim($warning);

        if ($warning === '') {
            throw new InvalidArgumentException('A research run warning cannot be blank.');
        }

        return DB::transaction(function () use ($run, $warning): ResearchRun {
            $lockedRun = ResearchRun::query()->lockForUpdate()->findOrFail($run->id);

            if ($lockedRun->status->isTerminal()) {
                return $lockedRun;
            }

            $warnings = array_values(array_unique([
                ...($lockedRun->collection_warnings ?? []),
                $warning,
            ]));

            $lockedRun->update(['collection_warnings' => $warnings]);

            return $lockedRun;
        });
    }
}
