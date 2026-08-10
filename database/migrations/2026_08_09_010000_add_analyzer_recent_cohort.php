<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analyzer_runs', function (Blueprint $table) {
            $table->unsignedSmallInteger('recent_video_limit')->default(30)->after('freshness_window_seconds');
            $table->string('uploads_playlist_id')->nullable()->after('channel_id');
            $table->string('cohort_next_page_token')->nullable()->after('uploads_playlist_id');
            $table->boolean('cohort_collection_complete')->default(false)->after('cohort_next_page_token');
        });

        Schema::create('analyzer_run_cohort_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analyzer_run_id')->constrained()->cascadeOnDelete();
            $table->string('provider_video_id', 64);
            $table->unsignedInteger('source_position');
            $table->timestamp('enriched_at')->nullable();
            $table->timestamp('unavailable_at')->nullable();
            $table->timestamps();

            $table->unique(['analyzer_run_id', 'provider_video_id'], 'analyzer_cohort_video_unique');
            $table->unique(['analyzer_run_id', 'source_position'], 'analyzer_cohort_position_unique');
            $table->index(['analyzer_run_id', 'enriched_at'], 'analyzer_cohort_enriched_index');
        });

        Schema::create('channel_analysis_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analyzer_run_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('channel_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('recent_valid_count');
            $table->unsignedSmallInteger('recent_requested_count');
            $table->decimal('coverage_percent', 8, 4);
            $table->decimal('median_views', 20, 4)->nullable();
            $table->decimal('average_views', 20, 4)->nullable();
            $table->unsignedBigInteger('minimum_views')->nullable();
            $table->unsignedBigInteger('maximum_views')->nullable();
            $table->decimal('median_likes', 20, 4)->nullable();
            $table->decimal('median_comments', 20, 4)->nullable();
            $table->decimal('median_duration_seconds', 16, 4)->nullable();
            $table->decimal('average_duration_seconds', 16, 4)->nullable();
            $table->unsignedInteger('minimum_duration_seconds')->nullable();
            $table->unsignedInteger('maximum_duration_seconds')->nullable();
            $table->decimal('median_age_days', 16, 4)->nullable();
            $table->decimal('average_upload_gap_days', 16, 6)->nullable();
            $table->decimal('median_upload_gap_days', 16, 6)->nullable();
            $table->decimal('longest_upload_gap_days', 16, 6)->nullable();
            $table->decimal('videos_per_week', 16, 6)->nullable();
            $table->decimal('videos_per_month', 16, 6)->nullable();
            $table->json('duration_distribution');
            $table->json('category_distribution');
            $table->string('calculation_version', 64);
            $table->json('input_summary');
            $table->json('warnings')->nullable();
            $table->timestamp('calculated_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_analysis_metrics');
        Schema::dropIfExists('analyzer_run_cohort_items');

        Schema::table('analyzer_runs', function (Blueprint $table) {
            $table->dropColumn([
                'recent_video_limit',
                'uploads_playlist_id',
                'cohort_next_page_token',
                'cohort_collection_complete',
            ]);
        });
    }
};
