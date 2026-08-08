<?php

namespace App\Domain\Exports\Actions;

use App\Domain\Exports\Enums\ExportStatus;
use App\Jobs\Exports\GenerateResearchExport;
use App\Models\ResearchExport;
use App\Models\User;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;

final class RetryResearchExport
{
    public function handle(User $user, ResearchExport $export): ResearchExport
    {
        if ($export->user_id !== $user->id) {
            throw new AuthorizationException;
        }

        if ($export->status !== ExportStatus::Failed) {
            throw new DomainException('Only failed exports can be retried.');
        }

        $export->update([
            'status' => ExportStatus::Queued,
            'started_at' => null,
            'failed_at' => null,
            'error_code' => null,
            'error_message' => null,
        ]);

        GenerateResearchExport::dispatch($export->id)->afterCommit();

        return $export->fresh() ?? $export;
    }
}
