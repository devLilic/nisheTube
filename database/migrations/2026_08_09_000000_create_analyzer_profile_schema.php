<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_categories', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 32);
            $table->string('category_id', 32);
            $table->string('region_key', 16)->default('global');
            $table->string('display_language', 16)->default('en');
            $table->string('name');
            $table->boolean('assignable')->default(true);
            $table->timestamp('fetched_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['provider', 'category_id', 'region_key', 'display_language'],
                'video_categories_provider_context_unique',
            );
        });

        Schema::create('analyzer_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('target_kind', 32)->default('video');
            $table->string('target_provider_id', 64);
            $table->foreignId('video_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('channel_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('collection_run_id')->constrained()->restrictOnDelete();
            $table->string('origin_kind', 32)->default('manual');
            $table->string('origin_reference')->nullable();
            $table->string('cache_policy', 32);
            $table->unsignedInteger('freshness_window_seconds');
            $table->string('calculation_version', 64)->default('video-profile-v1');
            $table->string('status', 32);
            $table->unsignedInteger('attempt_number')->default(1);
            $table->unsignedTinyInteger('progress_percent')->default(0);
            $table->json('warnings')->nullable();
            $table->string('error_code', 100)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('calculated_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->unique('collection_run_id', 'analyzer_runs_collection_run_unique');
            $table->index(['user_id', 'created_at'], 'analyzer_runs_owner_created_index');
            $table->index(['user_id', 'target_provider_id', 'created_at'], 'analyzer_runs_owner_target_index');
            $table->index(['status', 'created_at'], 'analyzer_runs_status_created_index');
        });

        Schema::create('analyzer_run_videos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analyzer_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('video_id')->constrained()->restrictOnDelete();
            $table->foreignId('video_snapshot_id')->constrained()->restrictOnDelete();
            $table->foreignId('channel_snapshot_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('role', 32);
            $table->unsignedInteger('source_position')->nullable();
            $table->timestamps();

            $table->unique(['analyzer_run_id', 'video_id', 'role'], 'analyzer_run_video_role_unique');
            $table->index(['analyzer_run_id', 'role'], 'analyzer_run_videos_role_index');
        });

        Schema::create('video_analysis_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analyzer_run_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('video_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('age_seconds');
            $table->decimal('lifetime_views_per_day', 20, 6)->nullable();
            $table->decimal('views_to_subscribers_ratio', 20, 8)->nullable();
            $table->decimal('like_rate_percent', 12, 6)->nullable();
            $table->decimal('comment_rate_percent', 12, 6)->nullable();
            $table->decimal('public_engagement_rate_percent', 12, 6)->nullable();
            $table->string('calculation_version', 64);
            $table->json('input_summary');
            $table->json('warnings')->nullable();
            $table->timestamp('calculated_at');
            $table->timestamps();
        });

        Schema::create('user_entity_observations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('subject_type', 16);
            $table->unsignedBigInteger('subject_id');
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamp('last_fetched_at');
            $table->foreignId('first_video_snapshot_id')->nullable()->constrained('video_snapshots')->nullOnDelete();
            $table->foreignId('latest_video_snapshot_id')->nullable()->constrained('video_snapshots')->nullOnDelete();
            $table->foreignId('first_channel_snapshot_id')->nullable()->constrained('channel_snapshots')->nullOnDelete();
            $table->foreignId('latest_channel_snapshot_id')->nullable()->constrained('channel_snapshots')->nullOnDelete();
            $table->unsignedBigInteger('first_observed_count')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'subject_type', 'subject_id'], 'user_entity_observations_subject_unique');
            $table->index(['user_id', 'last_seen_at'], 'user_entity_observations_owner_seen_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_entity_observations');
        Schema::dropIfExists('video_analysis_metrics');
        Schema::dropIfExists('analyzer_run_videos');
        Schema::dropIfExists('analyzer_runs');
        Schema::dropIfExists('video_categories');
    }
};
