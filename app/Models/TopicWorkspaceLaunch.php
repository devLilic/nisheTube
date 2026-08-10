<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $topic_workspace_id
 * @property int $user_id
 * @property string $launch_type
 * @property int|null $research_run_id
 * @property int|null $discovery_run_id
 * @property ResearchRun|null $researchRun
 * @property DiscoveryRun|null $discoveryRun
 * @property Carbon|null $created_at
 */
#[Fillable(['topic_workspace_id', 'user_id', 'launch_type', 'research_run_id', 'discovery_run_id'])]
class TopicWorkspaceLaunch extends Model
{
    /** @return BelongsTo<TopicWorkspace, $this> */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(TopicWorkspace::class, 'topic_workspace_id');
    }

    /** @return BelongsTo<ResearchRun, $this> */
    public function researchRun(): BelongsTo
    {
        return $this->belongsTo(ResearchRun::class);
    }

    /** @return BelongsTo<DiscoveryRun, $this> */
    public function discoveryRun(): BelongsTo
    {
        return $this->belongsTo(DiscoveryRun::class);
    }
}
