<?php

namespace App\Domain\Exports\Actions;

use App\Domain\Exports\Enums\ExportFormat;
use App\Domain\Exports\Enums\ExportStatus;
use App\Domain\Exports\Services\ResearchExportColumns;
use App\Domain\Research\Enums\ResearchRunStatus;
use App\Jobs\Exports\GenerateResearchExport;
use App\Models\ResearchExport;
use App\Models\ResearchRun;
use App\Models\User;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CreateResearchExport
{
    public function __construct(private readonly ResearchExportColumns $columns) {}

    /**
     * @param  list<string>  $researchRunPublicIds
     * @param  list<string>|null  $columnKeys
     *
     * @throws AuthorizationException
     * @throws DomainException
     */
    public function handle(User $user, ExportFormat $format, array $researchRunPublicIds, ?array $columnKeys = null): ResearchExport
    {
        $publicIds = $this->normalizePublicIds($researchRunPublicIds);
        $maxRuns = max(1, (int) config('exports.max_research_runs', 100));

        if ($publicIds === [] || count($publicIds) > $maxRuns) {
            throw new DomainException("Select between 1 and {$maxRuns} research runs to export.");
        }

        $runs = ResearchRun::query()
            ->whereIn('public_id', $publicIds)
            ->get(['id', 'public_id', 'user_id', 'status']);

        if ($runs->count() !== count($publicIds)) {
            throw new DomainException('One or more selected research runs are unavailable.');
        }

        if ($runs->contains(fn (ResearchRun $run): bool => $run->user_id !== $user->id)) {
            throw new AuthorizationException;
        }

        if ($runs->contains(fn (ResearchRun $run): bool => $run->status !== ResearchRunStatus::Completed)) {
            throw new DomainException('Only completed research runs can be exported.');
        }

        $selectedColumns = $this->columns->validate($columnKeys ?? $this->columns->all());

        return DB::transaction(function () use ($user, $format, $publicIds, $selectedColumns): ResearchExport {
            $export = ResearchExport::query()->create([
                'user_id' => $user->id,
                'format' => $format,
                'status' => ExportStatus::Queued,
                'selection' => [
                    'type' => 'research_runs',
                    'research_run_ids' => $publicIds,
                    'columns' => $selectedColumns,
                ],
            ]);

            GenerateResearchExport::dispatch($export->id)->afterCommit();

            return $export;
        });
    }

    /**
     * @param  list<string>  $publicIds
     * @return list<string>
     */
    private function normalizePublicIds(array $publicIds): array
    {
        $normalized = [];

        foreach ($publicIds as $publicId) {
            $publicId = Str::lower(trim($publicId));

            if (! Str::isUuid($publicId)) {
                throw new DomainException('Every selected research run must use a valid identifier.');
            }

            $normalized[$publicId] = $publicId;
        }

        return array_values($normalized);
    }
}
