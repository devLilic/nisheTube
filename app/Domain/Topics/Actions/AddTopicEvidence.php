<?php

namespace App\Domain\Topics\Actions;

use App\Domain\Topics\Enums\TopicEvidenceRole;
use App\Domain\Topics\Enums\TopicEvidenceType;
use App\Domain\Topics\Services\ResolveTopicEvidence;
use App\Models\TopicWorkspace;
use App\Models\TopicWorkspaceItem;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

final class AddTopicEvidence
{
    public function __construct(private readonly ResolveTopicEvidence $resolve) {}

    public function handle(User $user, TopicWorkspace $workspace, TopicEvidenceType $type, string $reference, TopicEvidenceRole $role, ?string $note): TopicWorkspaceItem
    {
        if ($workspace->user_id !== $user->id || $workspace->archived_at !== null) {
            throw new AuthorizationException;
        }

        $target = $this->resolve->handle($user, $type, $reference);

        return $workspace->items()->firstOrCreate(
            ['target_type' => $type->value, 'target_id' => $target->getKey()],
            ['evidence_role' => $role, 'note' => $note, 'sort_position' => ((int) $workspace->items()->max('sort_position')) + 1],
        );
    }
}
