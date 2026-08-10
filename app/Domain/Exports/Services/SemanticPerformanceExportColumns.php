<?php

namespace App\Domain\Exports\Services;

use DomainException;

final class SemanticPerformanceExportColumns
{
    /** @var array<string, string> */
    private const DEFINITIONS = [
        'analyzer_run_id' => 'Analyzer run ID',
        'performance_profile_id' => 'Performance profile ID',
        'calculation_version' => 'Performance version',
        'topic_version' => 'Topic version',
        'title_pattern_version' => 'Title-pattern version',
        'calculated_at' => 'Calculated at (UTC)',
        'group_type' => 'Group type',
        'group_label' => 'Group label',
        'group_key' => 'Group key',
        'unclassified' => 'Unclassified',
        'meets_minimum_sample' => 'Meets minimum sample',
        'minimum_sample_size' => 'Minimum sample size',
        'video_count' => 'Video count',
        'view_sample_count' => 'View sample count',
        'median_views' => 'Median views',
        'average_views' => 'Average views',
        'views_per_day_sample_count' => 'Lifetime views/day sample count',
        'median_views_per_day' => 'Median Lifetime Average Views/Day',
        'average_views_per_day' => 'Average Lifetime Average Views/Day',
        'breakout_sample_count' => 'Breakout-class sample count',
        'breakout_count' => 'Breakout count',
        'breakout_rate_percent' => 'Breakout rate (%)',
        'evidence_video_ids' => 'Evidence video IDs',
        'association_warning' => 'Interpretation warning',
    ];

    /** @return list<string> */
    public function all(): array
    {
        return array_keys(self::DEFINITIONS);
    }

    /** @param list<string> $columns
     * @return list<string>
     */
    public function validate(array $columns): array
    {
        $columns = array_values(array_unique($columns));
        if ($columns === [] || array_diff($columns, $this->all()) !== []) {
            throw new DomainException('The semantic performance export contains an unsupported column selection.');
        }

        return $columns;
    }

    /** @param list<string> $columns
     * @return list<string>
     */
    public function labels(array $columns): array
    {
        return array_map(fn (string $column): string => self::DEFINITIONS[$column], $columns);
    }
}
