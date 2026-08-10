<?php

namespace App\Domain\Audience\Actions;

use App\Domain\Comments\Enums\CommentCollectionStatus;
use App\Models\AnalyzerRun;
use App\Models\AudienceSignalProfile;
use App\Models\User;
use DomainException;

final readonly class CalculateLatestAudienceSignals
{
    public function __construct(private CalculateAudienceSignals $calculate) {}

    public function handle(User $user, AnalyzerRun $run): AudienceSignalProfile
    {
        if ($run->user_id !== $user->id) {
            throw new DomainException('The Analyzer run is not owned by this user.');
        }

        $collection = $run->commentCollections()
            ->where('user_id', $user->id)
            ->whereIn('status', [CommentCollectionStatus::Completed->value, CommentCollectionStatus::Partial->value])
            ->whereHas('comments')
            ->latest('id')
            ->first();
        if ($collection === null) {
            throw new DomainException('A completed or partial stored comment sample is required.');
        }

        return $this->calculate->handle($collection);
    }
}
