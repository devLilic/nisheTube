<?php

namespace App\Domain\Exports\Services;

use DomainException;

final class ResearchExportColumns
{
    /** @var array<string, array{label: string, group: string}> */
    private const DEFINITIONS = [
        'run_id' => ['label' => 'Run ID', 'group' => 'Research context'],
        'query' => ['label' => 'Query', 'group' => 'Research context'],
        'market' => ['label' => 'Market', 'group' => 'Research context'],
        'region' => ['label' => 'Region', 'group' => 'Research context'],
        'relevance_language' => ['label' => 'Relevance language', 'group' => 'Research context'],
        'collection_parameters' => ['label' => 'Collection parameters', 'group' => 'Research context'],
        'requested_results' => ['label' => 'Requested results', 'group' => 'Research context'],
        'collected_results' => ['label' => 'Collected results', 'group' => 'Research context'],
        'collection_warnings' => ['label' => 'Collection warnings', 'group' => 'Research context'],
        'run_completed_at' => ['label' => 'Run completed at (UTC)', 'group' => 'Research context'],
        'score_formula' => ['label' => 'Score formula version', 'group' => 'Score'],
        'score_calculated_at' => ['label' => 'Score calculated at (UTC)', 'group' => 'Score'],
        'opportunity_score' => ['label' => 'Opportunity score', 'group' => 'Score'],
        'confidence_score' => ['label' => 'Confidence score', 'group' => 'Score'],
        'score_warnings' => ['label' => 'Score warnings', 'group' => 'Score'],
        'video_id' => ['label' => 'Video ID', 'group' => 'Video performance'],
        'video_title' => ['label' => 'Video title', 'group' => 'Video performance'],
        'video_url' => ['label' => 'Video URL', 'group' => 'Video performance'],
        'video_published_at' => ['label' => 'Video published at (UTC)', 'group' => 'Video performance'],
        'result_rank' => ['label' => 'Result rank', 'group' => 'Video performance'],
        'video_metrics_collected_at' => ['label' => 'Video metrics collected at (UTC)', 'group' => 'Video performance'],
        'views' => ['label' => 'Views', 'group' => 'Video performance'],
        'likes' => ['label' => 'Likes', 'group' => 'Video performance'],
        'comments' => ['label' => 'Comments', 'group' => 'Video performance'],
        'views_per_day' => ['label' => 'Views per day', 'group' => 'Video performance'],
        'views_to_subscribers_ratio' => ['label' => 'Views to subscribers ratio', 'group' => 'Video performance'],
        'channel_id' => ['label' => 'Channel ID', 'group' => 'Channel metrics'],
        'channel_title' => ['label' => 'Channel title', 'group' => 'Channel metrics'],
        'channel_url' => ['label' => 'Channel URL', 'group' => 'Channel metrics'],
        'channel_metrics_collected_at' => ['label' => 'Channel metrics collected at (UTC)', 'group' => 'Channel metrics'],
        'subscribers' => ['label' => 'Subscribers', 'group' => 'Channel metrics'],
        'subscribers_hidden' => ['label' => 'Subscribers hidden', 'group' => 'Channel metrics'],
        'channel_views' => ['label' => 'Channel views', 'group' => 'Channel metrics'],
        'channel_videos' => ['label' => 'Channel videos', 'group' => 'Channel metrics'],
    ];

    /** @return list<string> */
    public function all(): array
    {
        return array_keys(self::DEFINITIONS);
    }

    /** @return list<string> */
    public function required(): array
    {
        return ['run_id', 'query', 'market', 'run_completed_at'];
    }

    /** @return array<string, list<array{key: string, label: string, required: bool}>> */
    public function grouped(): array
    {
        $groups = [];

        foreach (self::DEFINITIONS as $key => $definition) {
            $groups[$definition['group']][] = [
                'key' => $key,
                'label' => $definition['label'],
                'required' => in_array($key, $this->required(), true),
            ];
        }

        return $groups;
    }

    /**
     * @param  list<string>  $columns
     * @return list<string>
     */
    public function validate(array $columns): array
    {
        $columns = array_values(array_unique($columns));

        if ($columns === [] || array_diff($columns, $this->all()) !== []) {
            throw new DomainException('The export contains an unsupported column selection.');
        }

        if (array_diff($this->required(), $columns) !== []) {
            throw new DomainException('Run ID, query, market, and completion time are required export columns.');
        }

        $selected = array_fill_keys($columns, true);

        return array_values(array_filter($this->all(), fn (string $key): bool => isset($selected[$key])));
    }

    /**
     * @param  list<string>  $columns
     * @return list<string>
     */
    public function labels(array $columns): array
    {
        return array_map(fn (string $key): string => self::DEFINITIONS[$key]['label'], $columns);
    }
}
