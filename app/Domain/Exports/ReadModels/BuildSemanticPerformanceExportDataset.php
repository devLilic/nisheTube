<?php

namespace App\Domain\Exports\ReadModels;

use App\Domain\Exports\Data\ExportDataset;
use App\Domain\Exports\Services\SemanticPerformanceExportColumns;
use App\Models\SemanticPerformanceProfile;
use App\Models\User;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use JsonException;

final readonly class BuildSemanticPerformanceExportDataset
{
    public function __construct(private SemanticPerformanceExportColumns $columns) {}

    /** @param list<string>|null $columnKeys */
    public function handle(User $user, string $profilePublicId, ?array $columnKeys = null): ExportDataset
    {
        $selectedColumns = $this->columns->validate($columnKeys ?? $this->columns->all());
        $profile = SemanticPerformanceProfile::query()
            ->with(['analyzerRun', 'aggregates'])
            ->where('public_id', $profilePublicId)
            ->first();
        if ($profile === null) {
            throw new DomainException('The selected semantic performance profile is no longer available.');
        }
        if ($profile->user_id !== $user->id) {
            throw new AuthorizationException;
        }
        if ($profile->status === 'failed') {
            throw new DomainException('Failed semantic performance profiles cannot be exported.');
        }

        $rows = [];
        foreach ($profile->aggregates as $aggregate) {
            $rows[] = $this->project([
                'analyzer_run_id' => $profile->analyzerRun->public_id,
                'performance_profile_id' => $profile->public_id,
                'calculation_version' => $profile->calculation_version,
                'topic_version' => $profile->topic_version,
                'title_pattern_version' => $profile->title_pattern_version,
                'calculated_at' => $profile->calculated_at->utc()->toIso8601String(),
                'group_type' => $aggregate->group_type,
                'group_label' => $aggregate->label,
                'group_key' => $aggregate->label_key,
                'unclassified' => $aggregate->is_unclassified,
                'meets_minimum_sample' => $aggregate->meets_minimum_sample,
                'minimum_sample_size' => $profile->minimum_sample_size,
                'video_count' => $aggregate->sample_count,
                'view_sample_count' => $aggregate->view_sample_count,
                'median_views' => $aggregate->median_views === null ? null : (float) $aggregate->median_views,
                'average_views' => $aggregate->average_views === null ? null : (float) $aggregate->average_views,
                'views_per_day_sample_count' => $aggregate->views_per_day_sample_count,
                'median_views_per_day' => $aggregate->median_views_per_day === null ? null : (float) $aggregate->median_views_per_day,
                'average_views_per_day' => $aggregate->average_views_per_day === null ? null : (float) $aggregate->average_views_per_day,
                'breakout_sample_count' => $aggregate->breakout_sample_count,
                'breakout_count' => $aggregate->breakout_count,
                'breakout_rate_percent' => $aggregate->breakout_rate_percent === null ? null : (float) $aggregate->breakout_rate_percent,
                'evidence_video_ids' => $this->json($aggregate->evidence_video_ids),
                'association_warning' => 'Observed association within one frozen recent cohort; not evidence of causation.',
            ], $selectedColumns);
        }

        return new ExportDataset($this->columns->labels($selectedColumns), $rows, 'Semantic performance');
    }

    /** @param array<string, bool|float|int|string|null> $row
     * @param  list<string>  $columns
     * @return list<bool|float|int|string|null>
     */
    private function project(array $row, array $columns): array
    {
        return array_map(fn (string $column): bool|float|int|string|null => $row[$column], $columns);
    }

    /** @param list<string> $value
     * @throws JsonException
     */
    private function json(array $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
