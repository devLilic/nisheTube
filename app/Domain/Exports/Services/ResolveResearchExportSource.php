<?php

namespace App\Domain\Exports\Services;

use App\Domain\Exports\Data\ExportSelectionInput;
use App\Domain\Library\Enums\LibraryTargetType;
use App\Domain\Research\Enums\ResearchRunStatus;
use App\Models\Favorite;
use App\Models\ResearchRun;
use App\Models\TopicWorkspace;
use App\Models\User;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class ResolveResearchExportSource
{
    /**
     * @return array{run_ids:list<string>,video_ids:list<string>,source:array<string,mixed>,run_manifest:list<array<string,mixed>>}
     *
     * @throws AuthorizationException
     * @throws DomainException
     */
    public function handle(User $user, ExportSelectionInput $input): array
    {
        if (! $input->confirmed) {
            throw new DomainException('Confirm the exact stored dataset before creating an export.');
        }

        $runIds = match ($input->sourceType) {
            'research_runs', 'comparison' => $this->normalizeIds($input->researchRunIds, 'research run'),
            'shortlist' => $this->shortlistRunIds($user),
            'topic_workspace' => $this->workspaceRunIds($user, $input->sourceId),
            default => throw new DomainException('The selected export dataset is unavailable.'),
        };

        $maxRuns = max(1, (int) config('exports.max_research_runs', 100));
        if ($runIds === [] || count($runIds) > $maxRuns) {
            throw new DomainException("Select between 1 and {$maxRuns} completed research runs to export.");
        }
        if ($input->sourceType === 'comparison' && (count($runIds) < 2 || count($runIds) > 5)) {
            throw new DomainException('A comparison export requires two to five completed research runs.');
        }

        $runs = ResearchRun::query()->whereIn('public_id', $runIds)->get([
            'id', 'public_id', 'user_id', 'status', 'completed_at', 'collection_run_id', 'updated_at',
        ]);
        if ($runs->count() !== count($runIds)) {
            throw new DomainException('One or more selected research runs are unavailable.');
        }
        if ($runs->contains(fn (ResearchRun $run): bool => $run->user_id !== $user->id)) {
            throw new AuthorizationException;
        }
        if ($runs->contains(fn (ResearchRun $run): bool => $run->status !== ResearchRunStatus::Completed)) {
            throw new DomainException('Only completed research runs can be exported.');
        }

        $byPublicId = $runs->keyBy('public_id');
        $orderedRuns = collect($runIds)->map(fn (string $id): ResearchRun => $byPublicId->get($id));
        $videoIds = $this->normalizeIds($input->videoIds, 'video');
        if ($videoIds !== []) {
            $availableVideoIds = ResearchRun::query()->whereIn('id', $orderedRuns->pluck('id'))
                ->with(['videoMemberships.video:id,provider_video_id'])
                ->get()->flatMap(fn (ResearchRun $run) => $run->videoMemberships)
                ->map(fn ($membership): string => $membership->video->provider_video_id)
                ->unique()->values()->all();
            if (array_diff($videoIds, $availableVideoIds) !== []) {
                throw new DomainException('One or more selected video rows are unavailable.');
            }
        }

        return [
            'run_ids' => $runIds,
            'video_ids' => $videoIds,
            'source' => [
                'type' => $input->sourceType,
                'reference' => $input->sourceId,
                'version' => $this->sourceVersion($user, $input->sourceType, $input->sourceId, $runIds),
                'captured_at' => Carbon::now()->utc()->toIso8601String(),
            ],
            'run_manifest' => $this->runManifest($orderedRuns),
        ];
    }

    /** @return list<string> */
    private function shortlistRunIds(User $user): array
    {
        return array_values(Favorite::query()->forUser($user)
            ->where('target_type', LibraryTargetType::ResearchRun->value)
            ->with('target:id,public_id')
            ->latest('updated_at')->get()
            ->map(fn (Favorite $favorite): ?string => $favorite->target instanceof ResearchRun ? $favorite->target->public_id : null)
            ->filter()->values()->all());
    }

    /** @return list<string> */
    private function workspaceRunIds(User $user, ?string $workspacePublicId): array
    {
        if (! is_string($workspacePublicId) || ! Str::isUuid($workspacePublicId)) {
            throw new DomainException('Select a Topic Workspace to export.');
        }
        $workspace = TopicWorkspace::query()->where('public_id', $workspacePublicId)->first();
        if ($workspace === null) {
            throw new DomainException('The selected Topic Workspace is unavailable.');
        }
        if ($workspace->user_id !== $user->id) {
            throw new AuthorizationException;
        }

        return array_values($workspace->items()->where('target_type', 'research_run')->with('target:id,public_id')->orderBy('sort_position')->get()
            ->map(fn ($item): ?string => $item->target instanceof ResearchRun ? $item->target->public_id : null)
            ->filter()->unique()->values()->all());
    }

    /** @param list<string> $ids
     * @return list<string>
     */
    private function normalizeIds(array $ids, string $label): array
    {
        $normalized = [];
        foreach ($ids as $id) {
            $id = trim($id);
            if ($id === '') {
                continue;
            }
            if ($label === 'research run' && ! Str::isUuid($id)) {
                throw new DomainException('Every selected research run must use a valid identifier.');
            }
            $normalized[$id] = $id;
        }

        return array_values($normalized);
    }

    /**
     * @param  Collection<int, ResearchRun>  $runs
     * @return list<array<string, int|string|null>>
     */
    private function runManifest(Collection $runs): array
    {
        $manifest = [];

        foreach ($runs as $run) {
            $manifest[] = [
                'public_id' => $run->public_id,
                'completed_at' => $run->completed_at?->utc()->toIso8601String(),
                'collection_run_id' => $run->collection_run_id,
                'version' => $run->updated_at?->utc()->toIso8601String(),
            ];
        }

        return $manifest;
    }

    /** @param list<string> $runIds */
    private function sourceVersion(User $user, string $sourceType, ?string $sourceId, array $runIds): string
    {
        $updatedAt = match ($sourceType) {
            'shortlist' => Favorite::query()->forUser($user)
                ->where('target_type', LibraryTargetType::ResearchRun->value)->max('updated_at'),
            'topic_workspace' => TopicWorkspace::query()->where('user_id', $user->id)
                ->where('public_id', $sourceId)->value('updated_at'),
            default => null,
        };

        return hash('sha256', implode('|', [
            $sourceType,
            $sourceId ?? '',
            is_scalar($updatedAt) ? (string) $updatedAt : '',
            ...$runIds,
        ]));
    }
}
