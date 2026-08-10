<?php

namespace App\Domain\Audience\Actions;

use App\Domain\Audience\Contracts\AudienceSignalProvider;
use App\Domain\Audience\Data\AudienceSignalInput;
use App\Models\AudienceSignalProfile;
use App\Models\CommentCollectionRun;
use Illuminate\Support\Facades\DB;
use Throwable;

final readonly class CalculateAudienceSignals
{
    public function __construct(private AudienceSignalProvider $provider) {}

    public function handle(CommentCollectionRun $collection): AudienceSignalProfile
    {
        $existing = AudienceSignalProfile::query()
            ->where('comment_collection_run_id', $collection->id)
            ->where('provider', $this->provider->name())
            ->where('algorithm_version', $this->provider->version())
            ->first();
        if ($existing !== null) {
            return $existing;
        }

        $comments = $collection->comments()->orderBy('id')->get();
        $inputs = [];
        foreach ($comments as $comment) {
            $inputs[] = new AudienceSignalInput($comment->id, $comment->text);
        }

        try {
            $result = $this->provider->analyze($inputs);
        } catch (Throwable) {
            return AudienceSignalProfile::query()->create([
                'user_id' => $collection->user_id,
                'analyzer_run_id' => $collection->analyzer_run_id,
                'comment_collection_run_id' => $collection->id,
                'status' => 'failed',
                'provenance' => 'inferred',
                'provider' => $this->provider->name(),
                'algorithm_version' => $this->provider->version(),
                'language' => 'und',
                'comment_count' => count($inputs),
                'usable_comment_count' => 0,
                'warnings' => ['Audience Signals could not be calculated from the stored comment sample.'],
                'calculated_at' => now(),
            ]);
        }

        return DB::transaction(function () use ($collection, $inputs, $result): AudienceSignalProfile {
            $profile = AudienceSignalProfile::query()->create([
                'user_id' => $collection->user_id,
                'analyzer_run_id' => $collection->analyzer_run_id,
                'comment_collection_run_id' => $collection->id,
                'status' => $result->status,
                'provenance' => 'inferred',
                'provider' => $this->provider->name(),
                'algorithm_version' => $this->provider->version(),
                'language' => $result->language,
                'comment_count' => count($inputs),
                'usable_comment_count' => $result->usableCommentCount,
                'confidence_score' => $result->confidenceScore,
                'warnings' => $result->warnings === [] ? null : $result->warnings,
                'calculated_at' => now(),
            ]);

            $positions = [];
            foreach ($result->signals as $resultSignal) {
                $positions[$resultSignal->kind] = ($positions[$resultSignal->kind] ?? 0) + 1;
                $signal = $profile->signals()->create([
                    'kind' => $resultSignal->kind,
                    'label' => $resultSignal->label,
                    'label_key' => $resultSignal->key,
                    'confidence' => $resultSignal->confidence,
                    'comment_count' => $resultSignal->commentCount,
                    'occurrence_count' => $resultSignal->occurrenceCount,
                    'position' => $positions[$resultSignal->kind],
                ]);
                $signal->evidenceComments()->sync($resultSignal->evidenceCommentIds);
            }

            return $profile->load('signals.evidenceComments');
        });
    }
}
