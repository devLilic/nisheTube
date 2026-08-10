<?php

namespace App\Jobs\Exports;

use App\Domain\Exports\Enums\ExportStatus;
use App\Domain\Exports\ReadModels\BuildResearchRunExportDataset;
use App\Domain\Exports\ReadModels\BuildSemanticPerformanceExportDataset;
use App\Domain\Exports\Services\ExportWriterManager;
use App\Models\ResearchExport;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

final class GenerateResearchExport implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 180;

    public int $uniqueFor = 600;

    public bool $failOnTimeout = true;

    public function __construct(public readonly int $researchExportId) {}

    /** @return list<int> */
    public function backoff(): array
    {
        return [5, 30];
    }

    /** @return list<WithoutOverlapping> */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("research-export:{$this->researchExportId}"))
                ->releaseAfter(5)
                ->expireAfter(240),
        ];
    }

    public function uniqueId(): string
    {
        return (string) $this->researchExportId;
    }

    public function handle(
        BuildResearchRunExportDataset $datasetBuilder,
        ExportWriterManager $writers,
        ?BuildSemanticPerformanceExportDataset $semanticPerformanceDatasetBuilder = null,
    ): void {
        $export = ResearchExport::query()->with('user')->find($this->researchExportId);

        if ($export === null || $export->status === ExportStatus::Failed) {
            return;
        }

        $disk = (string) config('exports.disk', 'local');
        $path = "exports/{$export->user_id}/{$export->public_id}.{$export->format->extension()}";

        if (
            $export->status === ExportStatus::Completed
            && $export->disk === $disk
            && $export->path === $path
            && Storage::disk($disk)->exists($path)
        ) {
            return;
        }

        $export->update([
            'status' => ExportStatus::Processing,
            'started_at' => $export->started_at ?? Date::now(),
            'failed_at' => null,
            'error_code' => null,
            'error_message' => null,
        ]);

        $temporaryPath = tempnam(sys_get_temp_dir(), 'nishetube-export-');

        if ($temporaryPath === false) {
            throw new RuntimeException('A temporary export file could not be created.');
        }

        if ($export->format->extension() === 'xlsx') {
            $xlsxPath = $temporaryPath.'.zip';
            unlink($temporaryPath);
            $temporaryPath = $xlsxPath;
        }

        try {
            $dataset = $this->selectionType($export) === 'semantic_performance'
                ? ($semanticPerformanceDatasetBuilder ?? app(BuildSemanticPerformanceExportDataset::class))
                    ->handle($export->user, $this->semanticPerformanceProfileId($export), $this->columnKeys($export))
                : $datasetBuilder->handle($export->user, $this->researchRunIds($export), $this->columnKeys($export));
            $writers->for($export->format)->write($dataset, $temporaryPath);
            $size = filesize($temporaryPath);
            $checksum = hash_file('sha256', $temporaryPath);
            $stream = fopen($temporaryPath, 'rb');

            if ($size === false || $checksum === false || $stream === false) {
                throw new RuntimeException('The generated export file could not be inspected.');
            }

            try {
                if (! Storage::disk($disk)->put($path, $stream)) {
                    throw new RuntimeException('The generated export file could not be stored.');
                }
            } finally {
                fclose($stream);
            }

            $completedAt = Date::now();
            $expiryDays = max(1, (int) config('exports.expiry_days', 7));

            $export->update([
                'status' => ExportStatus::Completed,
                'disk' => $disk,
                'path' => $path,
                'size_bytes' => $size,
                'checksum_sha256' => $checksum,
                'completed_at' => $completedAt,
                'expires_at' => $completedAt->addDays($expiryDays),
                'failed_at' => null,
                'error_code' => null,
                'error_message' => null,
            ]);
        } catch (Throwable $exception) {
            Storage::disk($disk)->delete($path);

            throw $exception;
        } finally {
            if (is_file($temporaryPath)) {
                unlink($temporaryPath);
            }
        }
    }

    public function failed(?Throwable $exception): void
    {
        $export = ResearchExport::query()->find($this->researchExportId);

        if ($export === null || $export->status === ExportStatus::Completed) {
            return;
        }

        if ($export->disk !== null && $export->path !== null) {
            Storage::disk($export->disk)->delete($export->path);
        }

        $export->update([
            'status' => ExportStatus::Failed,
            'failed_at' => Date::now(),
            'error_code' => 'export_generation_failed',
            'error_message' => 'The export could not be generated. Your saved research is unchanged and the export can be retried.',
            'disk' => null,
            'path' => null,
            'size_bytes' => null,
            'checksum_sha256' => null,
            'completed_at' => null,
            'expires_at' => null,
        ]);
    }

    /** @return list<string> */
    private function researchRunIds(ResearchExport $export): array
    {
        $runIds = $export->selection['research_run_ids'] ?? null;

        if (! is_array($runIds)) {
            throw new RuntimeException('The stored export selection is invalid.');
        }

        $validated = [];

        foreach ($runIds as $runId) {
            if (! is_string($runId)) {
                throw new RuntimeException('The stored export selection is invalid.');
            }

            $validated[] = $runId;
        }

        return $validated;
    }

    private function selectionType(ResearchExport $export): string
    {
        $type = $export->selection['type'] ?? 'research_runs';

        if (! is_string($type) || ! in_array($type, ['research_runs', 'semantic_performance'], true)) {
            throw new RuntimeException('The stored export selection type is invalid.');
        }

        return $type;
    }

    private function semanticPerformanceProfileId(ResearchExport $export): string
    {
        $profileId = $export->selection['semantic_performance_profile_id'] ?? null;

        if (! is_string($profileId)) {
            throw new RuntimeException('The stored semantic performance export selection is invalid.');
        }

        return $profileId;
    }

    /** @return list<string>|null */
    private function columnKeys(ResearchExport $export): ?array
    {
        $columns = $export->selection['columns'] ?? null;

        if ($columns === null) {
            return null;
        }

        if (! is_array($columns)) {
            throw new RuntimeException('The stored export column selection is invalid.');
        }

        foreach ($columns as $column) {
            if (! is_string($column)) {
                throw new RuntimeException('The stored export column selection is invalid.');
            }
        }

        return array_values($columns);
    }
}
