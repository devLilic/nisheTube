<?php

namespace App\Http\ViewModels;

use App\Domain\Exports\Enums\ExportStatus;
use App\Domain\Exports\Services\ResearchExportColumns;
use App\Domain\Research\Enums\ResearchRunStatus;
use App\Models\Favorite;
use App\Models\OpportunityScore;
use App\Models\ResearchExport;
use App\Models\ResearchRun;
use App\Models\User;
use Illuminate\Support\Facades\Date;

final readonly class ExportViewModel
{
    public function __construct(private ResearchExportColumns $columns) {}

    /** @return array<string, mixed> */
    public function builder(User $user): array
    {
        return [
            'runs' => ResearchRun::query()
                ->where('user_id', $user->id)
                ->where('status', ResearchRunStatus::Completed)
                ->withCount('videoSnapshots')
                ->with(['videoMemberships.video:id,provider_video_id,title'])
                ->with(['opportunityScores' => fn ($query) => $query->latest('calculated_at')->latest('id')])
                ->latest('completed_at')
                ->limit(100)
                ->get()
                ->map(fn (ResearchRun $run): array => $this->run($run))
                ->values()
                ->all(),
            'column_groups' => $this->columns->grouped(),
            'default_columns' => $this->columns->all(),
            'standard_columns' => $this->columns->defaults(false),
            'max_runs' => max(1, (int) config('exports.max_research_runs', 100)),
            'expiry_days' => max(1, (int) config('exports.expiry_days', 7)),
            'shortlist_run_ids' => Favorite::query()->forUser($user)->where('target_type', 'research_run')
                ->with('target:id,public_id')->latest('updated_at')->get()
                ->map(fn (Favorite $favorite): ?string => $favorite->target instanceof ResearchRun ? $favorite->target->public_id : null)
                ->filter()->values()->all(),
            'topic_workspaces' => $user->topicWorkspaces()->with(['items' => fn ($query) => $query->where('target_type', 'research_run')->with('target:id,public_id')])
                ->orderBy('name')->get()->map(fn ($workspace): array => [
                    'public_id' => $workspace->public_id,
                    'name' => $workspace->name,
                    'run_ids' => $workspace->items->map(fn ($item): ?string => $item->target instanceof ResearchRun ? $item->target->public_id : null)->filter()->unique()->values()->all(),
                ])->all(),
        ];
    }

    /** @return array<string, mixed> */
    public function jobs(User $user): array
    {
        $exports = ResearchExport::query()
            ->where('user_id', $user->id)
            ->latest()
            ->limit(100)
            ->get();

        return [
            'items' => $exports->map(fn (ResearchExport $export): array => $this->export($export))->values()->all(),
            'has_active' => $exports->contains(fn (ResearchExport $export): bool => in_array($export->status, [ExportStatus::Queued, ExportStatus::Processing], true)),
        ];
    }

    /** @return array<string, mixed> */
    private function run(ResearchRun $run): array
    {
        $score = $run->opportunityScores->first();

        return [
            'public_id' => $run->public_id,
            'query_text' => $run->query_text,
            'market_key' => $run->market_key,
            'completed_at' => $run->completed_at?->toIso8601String(),
            'video_count' => (int) $run->getAttribute('video_snapshots_count'),
            'warning_count' => count($run->collection_warnings ?? []),
            'score' => $score instanceof OpportunityScore ? (float) $score->overall_score : null,
            'confidence' => $score instanceof OpportunityScore ? (float) $score->confidence_score : null,
            'videos' => $run->videoMemberships->map(fn ($membership): array => [
                'id' => $membership->video->provider_video_id,
                'title' => $membership->video->title,
                'rank' => $membership->result_rank,
            ])->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function export(ResearchExport $export): array
    {
        $expired = $export->expires_at !== null && $export->expires_at->lte(Date::now());
        $runIds = $export->selection['research_run_ids'] ?? [];
        $columns = $export->selection['columns'] ?? $this->columns->all();
        $selectionType = $export->selection['type'] ?? 'research_runs';
        $sourceType = $export->selection['source_manifest']['type'] ?? $selectionType;
        $videoIds = $export->selection['video_ids'] ?? [];

        return [
            'public_id' => $export->public_id,
            'format' => $export->format->value,
            'status' => $export->status->value,
            'run_count' => is_array($runIds) ? count($runIds) : 0,
            'selection_type' => is_string($sourceType) ? $sourceType : 'research_runs',
            'selection_label' => $selectionType === 'semantic_performance'
                ? '1 Analyzer performance profile'
                : $this->selectionLabel($sourceType, is_array($runIds) ? count($runIds) : 0, is_array($videoIds) ? count($videoIds) : 0),
            'column_count' => is_array($columns) ? count($columns) : 0,
            'size_bytes' => $export->size_bytes,
            'created_at' => $export->created_at?->toIso8601String(),
            'started_at' => $export->started_at?->toIso8601String(),
            'completed_at' => $export->completed_at?->toIso8601String(),
            'expires_at' => $export->expires_at?->toIso8601String(),
            'expired' => $expired,
            'can_download' => $export->status === ExportStatus::Completed && ! $expired && $export->path !== null,
            'error_message' => $export->error_message,
        ];
    }

    private function selectionLabel(mixed $sourceType, int $runCount, int $videoCount): string
    {
        $label = match ($sourceType) {
            'shortlist' => 'Shortlist',
            'comparison' => 'Comparison',
            'topic_workspace' => 'Topic Workspace',
            default => 'Research runs',
        };

        return $label.' · '.$runCount.' run(s)'.($videoCount > 0 ? ' · '.$videoCount.' selected video row(s)' : '');
    }
}
