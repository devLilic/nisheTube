<?php

namespace App\Models;

use App\Domain\Topics\Enums\TopicEvidenceRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property int $topic_workspace_id
 * @property string $target_type
 * @property int $target_id
 * @property TopicEvidenceRole $evidence_role
 * @property string|null $note
 * @property int $sort_position
 * @property Model|null $target
 */
#[Fillable(['topic_workspace_id', 'target_type', 'target_id', 'evidence_role', 'note', 'sort_position'])]
class TopicWorkspaceItem extends Model
{
    /** @return BelongsTo<TopicWorkspace, $this> */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(TopicWorkspace::class, 'topic_workspace_id');
    }

    /** @return MorphTo<Model, $this> */
    public function target(): MorphTo
    {
        return $this->morphTo();
    }

    protected function casts(): array
    {
        return ['evidence_role' => TopicEvidenceRole::class, 'sort_position' => 'integer'];
    }
}
