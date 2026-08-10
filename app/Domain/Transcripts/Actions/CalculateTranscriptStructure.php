<?php

namespace App\Domain\Transcripts\Actions;

use App\Domain\Transcripts\Contracts\TranscriptStructureProvider;
use App\Domain\Transcripts\Data\TranscriptStructureSegment;
use App\Models\AnalyzerRun;
use App\Models\TranscriptDocument;
use App\Models\TranscriptStructureProfile;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Throwable;

final readonly class CalculateTranscriptStructure
{
    public function __construct(private TranscriptStructureProvider $provider) {}

    public function handle(User $user, AnalyzerRun $run, TranscriptDocument $document): TranscriptStructureProfile
    {
        if ($run->user_id !== $user->id || $document->user_id !== $user->id || $document->analyzer_run_id !== $run->id) {
            throw new DomainException('The transcript revision is not owned by this Analyzer result.');
        }

        $existing = TranscriptStructureProfile::query()
            ->where('transcript_document_id', $document->id)
            ->where('provider', $this->provider->name())
            ->where('algorithm_version', $this->provider->version())
            ->first();
        if ($existing !== null) {
            return $existing;
        }

        $document->loadMissing('segments');
        $offset = 0;
        $segments = [];
        foreach ($document->segments as $segment) {
            $length = mb_strlen($segment->text, 'UTF-8');
            $segments[] = new TranscriptStructureSegment(
                $segment->position,
                $segment->text,
                $offset,
                $offset + $length,
                $segment->start_ms,
                $segment->end_ms,
            );
            $offset += $length + 1;
        }

        try {
            $result = $this->provider->analyze($document->plain_text, $document->language, $segments);
        } catch (Throwable) {
            return TranscriptStructureProfile::query()->firstOrCreate([
                'transcript_document_id' => $document->id,
                'provider' => $this->provider->name(),
                'algorithm_version' => $this->provider->version(),
            ], [
                'user_id' => $user->id,
                'analyzer_run_id' => $run->id,
                'status' => 'failed',
                'provenance' => 'inferred',
                'language' => $document->language,
                'word_count' => 0,
                'evidence_count' => 0,
                'warnings' => ['Transcript structure could not be calculated from this stored revision.'],
                'calculated_at' => now(),
            ]);
        }

        return DB::transaction(function () use ($user, $run, $document, $result): TranscriptStructureProfile {
            $profile = TranscriptStructureProfile::query()->firstOrCreate([
                'transcript_document_id' => $document->id,
                'provider' => $this->provider->name(),
                'algorithm_version' => $this->provider->version(),
            ], [
                'user_id' => $user->id,
                'analyzer_run_id' => $run->id,
                'status' => $result->status,
                'provenance' => 'inferred',
                'language' => $result->language,
                'word_count' => $result->wordCount,
                'evidence_count' => count($result->insights),
                'confidence_score' => $result->confidenceScore,
                'warnings' => $result->warnings === [] ? null : $result->warnings,
                'calculated_at' => now(),
            ]);
            if (! $profile->wasRecentlyCreated) {
                return $profile->load('insights');
            }

            $positions = [];
            foreach ($result->insights as $insight) {
                $positions[$insight->kind] = ($positions[$insight->kind] ?? 0) + 1;
                $profile->insights()->create([
                    'kind' => $insight->kind,
                    'label' => $insight->label,
                    'detail' => $insight->detail,
                    'confidence' => $insight->confidence,
                    'position' => $positions[$insight->kind],
                    'start_offset' => $insight->startOffset,
                    'end_offset' => $insight->endOffset,
                    'start_ms' => $insight->startMs,
                    'end_ms' => $insight->endMs,
                ]);
            }

            return $profile->load('insights');
        });
    }
}
