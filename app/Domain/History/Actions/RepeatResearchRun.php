<?php

namespace App\Domain\History\Actions;

use App\Domain\Research\Actions\CreateResearchRun;
use App\Domain\Research\Actions\QueueResearchRun;
use App\Models\ResearchRun;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class RepeatResearchRun
{
    public function __construct(
        private readonly CreateResearchRun $createResearchRun,
        private readonly QueueResearchRun $queueResearchRun,
    ) {}

    /** @throws AuthorizationException */
    public function handle(User $user, ResearchRun $source, string $submissionToken): ResearchRun
    {
        if ($source->user_id !== $user->id) {
            throw new AuthorizationException;
        }

        $existing = $this->existing($user, $submissionToken);

        if ($existing !== null) {
            return $existing;
        }

        try {
            return DB::transaction(function () use ($user, $source, $submissionToken): ResearchRun {
                $existing = $this->existing($user, $submissionToken);

                if ($existing !== null) {
                    return $existing;
                }

                $source = ResearchRun::query()
                    ->with('researchQuery')
                    ->findOrFail($source->id);

                if ($source->user_id !== $user->id) {
                    throw new AuthorizationException;
                }

                $repeat = $this->createResearchRun->handle(
                    user: $user,
                    query: $source->researchQuery,
                    requestedResultCount: $source->requested_result_count,
                    kind: $source->kind,
                    submissionToken: $submissionToken,
                    intakeContext: $source->parameters,
                );

                return $this->queueResearchRun->handle($user, $repeat);
            });
        } catch (QueryException $exception) {
            $existing = $this->existing($user, $submissionToken);

            if ($existing !== null) {
                return $existing;
            }

            throw $exception;
        }
    }

    private function existing(User $user, string $submissionToken): ?ResearchRun
    {
        return ResearchRun::query()
            ->where('user_id', $user->id)
            ->where('submission_token', $submissionToken)
            ->first();
    }
}
