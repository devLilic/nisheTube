<?php

namespace App\Domain\Exports\Actions;

use App\Domain\Analyzer\Enums\AnalyzerRunStatus;
use App\Domain\Exports\Enums\ExportFormat;
use App\Domain\Exports\Enums\ExportStatus;
use App\Domain\Exports\Services\SemanticPerformanceExportColumns;
use App\Jobs\Exports\GenerateResearchExport;
use App\Models\AnalyzerRun;
use App\Models\ResearchExport;
use App\Models\SemanticPerformanceProfile;
use App\Models\User;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final readonly class CreateSemanticPerformanceExport
{
    public function __construct(private SemanticPerformanceExportColumns $columns) {}

    public function handle(User $user, AnalyzerRun $run, ExportFormat $format): ResearchExport
    {
        if ($run->user_id !== $user->id) {
            throw new AuthorizationException;
        }
        if ($run->status !== AnalyzerRunStatus::Completed) {
            throw new DomainException('Only completed Analyzer attempts can be exported.');
        }

        $profile = SemanticPerformanceProfile::query()
            ->where('user_id', $user->id)
            ->where('analyzer_run_id', $run->id)
            ->first();
        if ($profile === null || $profile->status === 'failed') {
            throw new DomainException('A calculated semantic performance profile is required before export.');
        }

        return DB::transaction(function () use ($user, $run, $profile, $format): ResearchExport {
            $export = ResearchExport::query()->create([
                'user_id' => $user->id,
                'format' => $format,
                'status' => ExportStatus::Queued,
                'selection' => [
                    'type' => 'semantic_performance',
                    'analyzer_run_id' => $run->public_id,
                    'semantic_performance_profile_id' => $profile->public_id,
                    'columns' => $this->columns->all(),
                ],
            ]);

            GenerateResearchExport::dispatch($export->id)->afterCommit();

            return $export;
        });
    }
}
