<?php

namespace App\Domain\Exports\Actions;

use App\Domain\Exports\Data\ExportSelectionInput;
use App\Domain\Exports\Enums\ExportFormat;
use App\Domain\Exports\Enums\ExportStatus;
use App\Domain\Exports\Services\ResearchExportColumns;
use App\Domain\Exports\Services\ResolveResearchExportSource;
use App\Jobs\Exports\GenerateResearchExport;
use App\Models\ResearchExport;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

final class CreateResearchExport
{
    public function __construct(
        private readonly ResearchExportColumns $columns,
        private readonly ResolveResearchExportSource $sources,
    ) {}

    /**
     * @throws DomainException
     */
    public function handle(User $user, ExportFormat $format, ExportSelectionInput $input): ResearchExport
    {
        $resolved = $this->sources->handle($user, $input);
        $selectedColumns = $this->columns->validate($input->columns ?? $this->columns->defaults($input->includeTechnicalDetails));

        return DB::transaction(function () use ($user, $format, $resolved, $selectedColumns, $input): ResearchExport {
            $export = ResearchExport::query()->create([
                'user_id' => $user->id,
                'format' => $format,
                'status' => ExportStatus::Queued,
                'selection' => [
                    'type' => 'research_runs',
                    'research_run_ids' => $resolved['run_ids'],
                    'video_ids' => $resolved['video_ids'],
                    'columns' => $selectedColumns,
                    'include_technical_details' => $input->includeTechnicalDetails,
                    'filters' => ['video_ids' => $resolved['video_ids']],
                    'source_manifest' => $resolved['source'],
                    'run_manifest' => $resolved['run_manifest'],
                ],
            ]);

            GenerateResearchExport::dispatch($export->id)->afterCommit();

            return $export;
        });
    }
}
