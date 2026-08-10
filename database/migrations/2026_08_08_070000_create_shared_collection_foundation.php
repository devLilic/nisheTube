<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collection_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 32);
            $table->string('kind', 64);
            $table->string('status', 32);
            $table->unsignedInteger('attempt_number')->default(1);
            $table->json('frozen_request');
            $table->string('cache_policy', 32);
            $table->json('requested_parts')->nullable();
            $table->json('configuration_context')->nullable();
            $table->json('safe_metadata')->nullable();
            $table->unsignedInteger('requested_count')->default(0);
            $table->unsignedInteger('processed_count')->default(0);
            $table->unsignedTinyInteger('progress_percent')->default(0);
            $table->json('warnings')->nullable();
            $table->string('error_code', 100)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at'], 'collection_runs_owner_created_index');
            $table->index(['status', 'created_at'], 'collection_runs_status_created_index');
            $table->index(['provider', 'kind', 'completed_at'], 'collection_runs_provider_kind_completed_index');
        });

        Schema::table('research_runs', function (Blueprint $table) {
            $table->foreignId('collection_run_id')
                ->nullable()
                ->after('research_query_id')
                ->constrained('collection_runs')
                ->restrictOnDelete();
            $table->unique('collection_run_id', 'research_runs_collection_run_unique');
        });

        Schema::table('video_snapshots', function (Blueprint $table) {
            $table->foreignId('collection_run_id')
                ->nullable()
                ->after('research_run_id')
                ->constrained('collection_runs')
                ->restrictOnDelete();
            $table->unique(['collection_run_id', 'video_id'], 'video_snapshots_collection_video_unique');
        });

        Schema::table('channel_snapshots', function (Blueprint $table) {
            $table->foreignId('collection_run_id')
                ->nullable()
                ->after('research_run_id')
                ->constrained('collection_runs')
                ->restrictOnDelete();
            $table->unique(['collection_run_id', 'channel_id'], 'channel_snapshots_collection_channel_unique');
        });

        Schema::table('research_run_videos', function (Blueprint $table) {
            $table->foreignId('video_snapshot_id')
                ->nullable()
                ->after('video_id')
                ->constrained('video_snapshots')
                ->nullOnDelete();
            $table->foreignId('channel_snapshot_id')
                ->nullable()
                ->after('video_snapshot_id')
                ->constrained('channel_snapshots')
                ->nullOnDelete();
            $table->index(['research_run_id', 'video_snapshot_id'], 'rr_videos_run_video_source_index');
            $table->index(['research_run_id', 'channel_snapshot_id'], 'rr_videos_run_channel_source_index');
        });

        Schema::table('api_usage_events', function (Blueprint $table) {
            $table->foreignId('collection_run_id')
                ->nullable()
                ->after('research_run_id')
                ->constrained('collection_runs')
                ->nullOnDelete();
            $table->index(['collection_run_id', 'occurred_at'], 'api_usage_collection_occurred_index');
        });

        $this->backfillResearchSources();
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();

        try {
            Schema::table('api_usage_events', function (Blueprint $table) {
                $table->dropForeign(['collection_run_id']);
                $table->dropIndex('api_usage_collection_occurred_index');
                $table->dropColumn('collection_run_id');
            });

            Schema::table('research_run_videos', function (Blueprint $table) {
                $table->dropForeign(['video_snapshot_id']);
                $table->dropForeign(['channel_snapshot_id']);
                $table->dropIndex('rr_videos_run_video_source_index');
                $table->dropIndex('rr_videos_run_channel_source_index');
                $table->dropColumn(['video_snapshot_id', 'channel_snapshot_id']);
            });

            Schema::table('channel_snapshots', function (Blueprint $table) {
                $table->dropForeign(['collection_run_id']);
                $table->dropUnique('channel_snapshots_collection_channel_unique');
                $table->dropColumn('collection_run_id');
            });

            Schema::table('video_snapshots', function (Blueprint $table) {
                $table->dropForeign(['collection_run_id']);
                $table->dropUnique('video_snapshots_collection_video_unique');
                $table->dropColumn('collection_run_id');
            });

            Schema::table('research_runs', function (Blueprint $table) {
                $table->dropForeign(['collection_run_id']);
                $table->dropUnique('research_runs_collection_run_unique');
                $table->dropColumn('collection_run_id');
            });

            Schema::dropIfExists('collection_runs');
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    private function backfillResearchSources(): void
    {
        DB::table('research_runs')
            ->orderBy('id')
            ->chunkById(100, function ($runs): void {
                foreach ($runs as $run) {
                    $status = $run->status;
                    $errorCode = $run->error_code;

                    if (! is_string($status) || (! is_string($errorCode) && $errorCode !== null)) {
                        throw new UnexpectedValueException('Research run status data must contain strings.');
                    }

                    $collectionStatus = $this->historicalCollectionStatus($status, $errorCode);
                    $completedAt = $collectionStatus === 'completed'
                        ? ($run->completed_at ?? $run->updated_at)
                        : null;
                    $failedAt = $collectionStatus === 'failed' ? $run->failed_at : null;
                    $collectionRunId = DB::table('collection_runs')->insertGetId([
                        'public_id' => (string) Str::uuid(),
                        'user_id' => $run->user_id,
                        'provider' => 'youtube',
                        'kind' => 'search_enrichment',
                        'status' => $collectionStatus,
                        'attempt_number' => $run->attempt_number,
                        'frozen_request' => json_encode([
                            'query_text' => $run->query_text,
                            'market_key' => $run->market_key,
                            'region_code' => $run->region_code,
                            'relevance_language' => $run->relevance_language,
                            'parameters' => $this->decodeJson($run->parameters),
                        ], JSON_THROW_ON_ERROR),
                        'cache_policy' => 'fresh_only',
                        'requested_parts' => json_encode([
                            'videos.snippet',
                            'videos.contentDetails',
                            'videos.statistics',
                            'channels.snippet',
                            'channels.statistics',
                        ], JSON_THROW_ON_ERROR),
                        'configuration_context' => null,
                        'safe_metadata' => json_encode([
                            'origin' => 'research',
                            'historical_backfill' => true,
                            'research_run_public_id' => $run->public_id,
                        ], JSON_THROW_ON_ERROR),
                        'requested_count' => $run->requested_result_count,
                        'processed_count' => $run->enriched_result_count,
                        'progress_percent' => $collectionStatus === 'completed'
                            ? 100
                            : min(100, max(0, (int) $run->progress_percent)),
                        'warnings' => $run->collection_warnings,
                        'error_code' => $collectionStatus === 'failed' ? $run->error_code : null,
                        'error_message' => $collectionStatus === 'failed' ? $run->error_message : null,
                        'started_at' => $run->started_at,
                        'completed_at' => $completedAt,
                        'failed_at' => $failedAt,
                        'created_at' => $run->created_at,
                        'updated_at' => $run->updated_at,
                    ]);

                    DB::table('research_runs')
                        ->where('id', $run->id)
                        ->update(['collection_run_id' => $collectionRunId]);

                    DB::table('video_snapshots')
                        ->where('research_run_id', $run->id)
                        ->update(['collection_run_id' => $collectionRunId]);

                    DB::table('channel_snapshots')
                        ->where('research_run_id', $run->id)
                        ->update(['collection_run_id' => $collectionRunId]);

                    DB::table('api_usage_events')
                        ->where('research_run_id', $run->id)
                        ->update(['collection_run_id' => $collectionRunId]);

                    $videoSources = DB::table('video_snapshots')
                        ->where('research_run_id', $run->id)
                        ->pluck('id', 'video_id');
                    $channelSources = DB::table('channel_snapshots')
                        ->where('research_run_id', $run->id)
                        ->pluck('id', 'channel_id');
                    $memberships = DB::table('research_run_videos as membership')
                        ->join('videos as video', 'video.id', '=', 'membership.video_id')
                        ->where('membership.research_run_id', $run->id)
                        ->get(['membership.video_id', 'video.channel_id']);

                    foreach ($memberships as $membership) {
                        DB::table('research_run_videos')
                            ->where('research_run_id', $run->id)
                            ->where('video_id', $membership->video_id)
                            ->update([
                                'video_snapshot_id' => $videoSources->get($membership->video_id),
                                'channel_snapshot_id' => $channelSources->get($membership->channel_id),
                            ]);
                    }
                }
            });
    }

    private function historicalCollectionStatus(string $status, ?string $errorCode): string
    {
        if (in_array($status, ['scoring', 'completed'], true)) {
            return 'completed';
        }

        if ($status === 'failed') {
            return $errorCode === 'research_scoring_failed' ? 'completed' : 'failed';
        }

        if (in_array($status, ['searching', 'enriching'], true)) {
            return 'collecting';
        }

        return 'queued';
    }

    /** @return array<string, mixed> */
    private function decodeJson(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value)) {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }
};
