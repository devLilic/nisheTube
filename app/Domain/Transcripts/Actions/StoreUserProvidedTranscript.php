<?php

namespace App\Domain\Transcripts\Actions;

use App\Domain\Analyzer\Enums\AnalyzerRunStatus;
use App\Domain\Transcripts\Contracts\TranscriptProvider;
use App\Domain\Transcripts\Data\TranscriptSegmentData;
use App\Models\AnalyzerRun;
use App\Models\TranscriptDocument;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class StoreUserProvidedTranscript
{
    public function __construct(private TranscriptProvider $provider) {}

    public function handle(User $user, AnalyzerRun $run, string $sourceText, string $language): TranscriptDocument
    {
        if ($run->user_id !== $user->id || $run->status !== AnalyzerRunStatus::Completed) {
            throw new DomainException('Transcripts can only be added to an owned completed Analyzer result.');
        }
        $membership = $run->videoMemberships()->where('role', 'anchor')->first();
        if ($run->target_kind !== 'video' || $membership === null) {
            throw new DomainException('A transcript can only be attached to a completed video analysis.');
        }

        $result = $this->provider->parse($sourceText);
        $checksum = hash('sha256', $sourceText);
        $existing = TranscriptDocument::query()
            ->where('user_id', $user->id)
            ->where('analyzer_run_id', $run->id)
            ->where('checksum_sha256', $checksum)
            ->first();
        if ($existing !== null) {
            return $existing;
        }

        return DB::transaction(function () use ($user, $run, $membership, $sourceText, $language, $result, $checksum): TranscriptDocument {
            AnalyzerRun::query()->lockForUpdate()->findOrFail($run->id);
            $duplicate = TranscriptDocument::query()
                ->where('user_id', $user->id)
                ->where('analyzer_run_id', $run->id)
                ->where('checksum_sha256', $checksum)
                ->first();
            if ($duplicate !== null) {
                return $duplicate->load('segments');
            }

            $document = TranscriptDocument::query()->create([
                'user_id' => $user->id,
                'analyzer_run_id' => $run->id,
                'video_id' => $membership->video_id,
                'provider' => $this->provider->name(),
                'provider_version' => $this->provider->version(),
                'status' => $result->status,
                'input_format' => $result->format,
                'language' => $language,
                'source_text' => $sourceText,
                'plain_text' => $result->plainText,
                'character_count' => mb_strlen($result->plainText),
                'segment_count' => count($result->segments),
                'checksum_sha256' => $checksum,
                'warnings' => $result->warnings === [] ? null : $result->warnings,
                'rights_confirmed_at' => now(),
                'provided_at' => now(),
            ]);

            foreach ($result->segments as $position => $segment) {
                $this->storeSegment($document, $segment, $position + 1);
            }

            return $document->load('segments');
        });
    }

    private function storeSegment(TranscriptDocument $document, TranscriptSegmentData $segment, int $position): void
    {
        $document->segments()->create([
            'position' => $position,
            'start_ms' => $segment->startMs,
            'end_ms' => $segment->endMs,
            'text' => $segment->text,
        ]);
    }
}
