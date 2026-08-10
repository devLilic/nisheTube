<?php

namespace App\Domain\Analyzer\Actions;

use App\Models\AnalyzerCuration;
use App\Models\AnalyzerRun;
use App\Models\User;
use DomainException;

final class UpdateAnalyzerCuration
{
    public function handle(
        User $user,
        AnalyzerRun $run,
        string $subjectType,
        string $researchStatus,
        ?string $note,
    ): AnalyzerCuration {
        if ($run->user_id !== $user->id) {
            throw new DomainException('Analyzer curation ownership does not match.');
        }

        $subjectId = match ($subjectType) {
            'video' => $run->video_id,
            'channel' => $run->channel_id,
            default => null,
        };

        if ($subjectId === null) {
            throw new DomainException('The selected Analyzer subject is unavailable.');
        }

        return AnalyzerCuration::query()->updateOrCreate([
            'user_id' => $user->id,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
        ], [
            'research_status' => $researchStatus,
            'note' => $note,
        ]);
    }
}
