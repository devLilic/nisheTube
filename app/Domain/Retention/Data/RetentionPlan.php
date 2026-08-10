<?php

namespace App\Domain\Retention\Data;

use App\Domain\Retention\Enums\CleanupTargetType;
use App\Domain\Retention\Enums\DeletionOutcome;
use Carbon\CarbonInterface;

final readonly class RetentionPlan
{
    /**
     * @param  array<string, int>  $counts
     * @param  list<array{target_type: CleanupTargetType, target_reference: string, original_collection_at: CarbonInterface, outcome: DeletionOutcome, favorite_impacted: bool}>  $items
     * @param  list<array{public_id: string, query_text: string, status: string, terminal_at: string, favorite_impacted: bool, shared_source_impacted: bool, video_snapshots: int, channel_snapshots: int, opportunity_scores: int, artifacts: int}>  $runs
     */
    public function __construct(
        public CarbonInterface $cutoffAt,
        public array $counts,
        public array $items,
        public array $runs,
    ) {}

    /** @return array{cutoff_at: string, counts: array<string, int>, runs: list<array{public_id: string, query_text: string, status: string, terminal_at: string, favorite_impacted: bool, shared_source_impacted: bool, video_snapshots: int, channel_snapshots: int, opportunity_scores: int, artifacts: int}>} */
    public function toArray(): array
    {
        return [
            'cutoff_at' => $this->cutoffAt->toIso8601String(),
            'counts' => $this->counts,
            'runs' => $this->runs,
        ];
    }
}
