<?php

namespace App\Domain\Comments\Actions;

use App\Domain\Library\Services\LibraryInputNormalizer;
use App\Models\NicheCandidate;
use App\Models\SavedCommentIdea;
use App\Models\TopicWorkspace;
use App\Models\User;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;

final readonly class UpdateSavedCommentIdea
{
    public function __construct(private LibraryInputNormalizer $normalize) {}

    public function handle(User $user, SavedCommentIdea $idea, ?TopicWorkspace $workspace, ?NicheCandidate $candidate, string $status, ?string $format, ?string $audience, ?string $decisionNote): SavedCommentIdea
    {
        if ($idea->user_id !== $user->id) {
            throw new AuthorizationException;
        }
        if ($workspace !== null && ($workspace->user_id !== $user->id || $workspace->archived_at !== null)) {
            throw new AuthorizationException;
        }
        if ($candidate !== null && ($candidate->discoveryRun->user_id !== $user->id || ($workspace !== null && $candidate->discoveryRun->market_key !== $workspace->market_key))) {
            throw new AuthorizationException;
        }
        if (! in_array($status, ['new', 'reviewing', 'selected', 'ruled_out'], true)) {
            throw new DomainException('The idea decision status is invalid.');
        }

        $idea->update([
            'topic_workspace_id' => $workspace?->id,
            'niche_candidate_id' => $candidate?->id,
            'decision_status' => $status,
            'format' => $this->normalize->optionalText($format, 120),
            'audience' => $this->normalize->optionalText($audience, 240),
            'decision_note' => $this->normalize->optionalText($decisionNote, 4000),
        ]);

        return $idea->fresh() ?? $idea;
    }
}
