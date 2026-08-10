<?php

namespace App\Domain\Transcripts\Actions;

use App\Models\TranscriptDeletionAudit;
use App\Models\TranscriptDocument;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

final class DeleteTranscript
{
    public function handle(User $user, TranscriptDocument $document): void
    {
        if ($document->user_id !== $user->id) {
            throw new DomainException('The transcript is not owned by this user.');
        }

        DB::transaction(function () use ($user, $document): void {
            $locked = TranscriptDocument::query()->lockForUpdate()->find($document->id);
            if ($locked === null || $locked->user_id !== $user->id) {
                throw new DomainException('The transcript is not owned by this user.');
            }

            TranscriptDeletionAudit::query()->create([
                'user_id' => $user->id,
                'analyzer_run_public_id' => (string) $locked->analyzerRun()->value('public_id'),
                'transcript_document_public_id' => $locked->public_id,
                'provider_video_id' => (string) $locked->video()->value('provider_video_id'),
                'provider_version' => $locked->provider_version,
                'input_format' => $locked->input_format,
                'language' => $locked->language,
                'character_count' => $locked->character_count,
                'segment_count' => $locked->segment_count,
                'deleted_at' => now(),
            ]);
            $locked->delete();
        });
    }
}
