<?php

namespace App\Domain\Discovery\Actions;

use App\Domain\Discovery\Enums\NicheCandidateStatus;
use App\Models\DiscoveryRun;
use App\Models\NicheCandidate;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class BulkDismissCandidates
{
    public const MAX_SELECTION = 10;

    public function __construct(private UpdateCandidateStatus $updateStatus) {}

    /**
     * @param  list<string>  $candidatePublicIds
     * @return array{dismissed: int, already_dismissed: int, validated: int}
     */
    public function handle(User $user, DiscoveryRun $run, array $candidatePublicIds): array
    {
        return DB::transaction(function () use ($user, $run, $candidatePublicIds): array {
            $candidates = NicheCandidate::query()
                ->where('discovery_run_id', $run->id)
                ->whereIn('public_id', $candidatePublicIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('public_id');

            if ($candidates->count() !== count($candidatePublicIds)) {
                throw ValidationException::withMessages([
                    'candidate_ids' => __('One or more selected candidates are unavailable.'),
                ]);
            }

            $result = ['dismissed' => 0, 'already_dismissed' => 0, 'validated' => 0];

            foreach ($candidatePublicIds as $publicId) {
                /** @var NicheCandidate $candidate */
                $candidate = $candidates->get($publicId);

                if ($candidate->status === NicheCandidateStatus::Validated) {
                    $result['validated']++;

                    continue;
                }

                if ($candidate->status === NicheCandidateStatus::Dismissed) {
                    $result['already_dismissed']++;

                    continue;
                }

                $this->updateStatus->handle($user, $candidate, NicheCandidateStatus::Dismissed);
                $result['dismissed']++;
            }

            return $result;
        });
    }
}
