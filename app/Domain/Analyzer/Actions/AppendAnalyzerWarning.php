<?php

namespace App\Domain\Analyzer\Actions;

use App\Models\AnalyzerRun;

final class AppendAnalyzerWarning
{
    public function handle(AnalyzerRun $run, string $warning): AnalyzerRun
    {
        $warnings = $run->warnings ?? [];

        if (! in_array($warning, $warnings, true)) {
            $warnings[] = $warning;
            $run->update(['warnings' => $warnings]);
        }

        return $run->fresh() ?? $run;
    }
}
