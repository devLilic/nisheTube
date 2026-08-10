<?php

namespace App\Domain\Topics\Services;

use App\Domain\Library\Enums\LibraryTargetType;
use App\Domain\Library\Services\ResolveLibraryTarget;
use App\Domain\Topics\Enums\TopicEvidenceType;
use App\Models\AnalyzerRun;
use App\Models\NicheCandidate;
use App\Models\ResearchQuery;
use App\Models\ResearchRun;
use App\Models\User;
use App\Models\WatchlistItem;
use Illuminate\Database\Eloquent\Model;

final class ResolveTopicEvidence
{
    public function __construct(private readonly ResolveLibraryTarget $libraryTargets) {}

    public function handle(User $user, TopicEvidenceType $type, string $reference): Model
    {
        return match ($type) {
            TopicEvidenceType::Video, TopicEvidenceType::Channel,
            TopicEvidenceType::ResearchQuery, TopicEvidenceType::ResearchRun,
            TopicEvidenceType::NicheCandidate => $this->libraryTargets->handle(
                $user,
                LibraryTargetType::from($type->value),
                $reference,
            ),
            TopicEvidenceType::AnalyzerRun => AnalyzerRun::query()
                ->where('user_id', $user->id)->where('public_id', $reference)->firstOrFail(),
            TopicEvidenceType::WatchlistItem => WatchlistItem::query()
                ->where('user_id', $user->id)->where('public_id', $reference)->firstOrFail(),
        };
    }

    public function marketKey(Model $target): ?string
    {
        return match (true) {
            $target instanceof ResearchRun => $target->market_key,
            $target instanceof ResearchQuery => $target->market()->value('key'),
            $target instanceof NicheCandidate => $target->discoveryRun()->value('market_key'),
            default => null,
        };
    }
}
