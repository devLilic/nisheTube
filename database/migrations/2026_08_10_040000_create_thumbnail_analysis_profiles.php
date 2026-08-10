<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('thumbnail_analysis_profiles', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('analyzer_run_id')->constrained()->cascadeOnDelete();
            $table->string('status', 32);
            $table->string('provenance', 32)->default('inferred');
            $table->string('provider', 64);
            $table->string('algorithm_version', 64);
            $table->string('calculation_version', 64);
            $table->unsignedSmallInteger('attempt_number');
            $table->unsignedSmallInteger('minimum_sample_size');
            $table->unsignedSmallInteger('cohort_video_count');
            $table->unsignedSmallInteger('processed_image_count')->default(0);
            $table->unsignedSmallInteger('available_image_count')->default(0);
            $table->unsignedSmallInteger('reused_image_count')->default(0);
            $table->unsignedSmallInteger('unavailable_image_count')->default(0);
            $table->decimal('confidence_score', 7, 4)->nullable();
            $table->json('warnings')->nullable();
            $table->string('error_code', 64)->nullable();
            $table->string('error_message', 500)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('calculated_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['analyzer_run_id', 'provider', 'algorithm_version', 'attempt_number'],
                'thumb_profiles_run_provider_version_attempt_unique',
            );
            $table->index(['user_id', 'analyzer_run_id', 'created_at'], 'thumb_profiles_owner_run_created_index');
        });

        Schema::create('thumbnail_analysis_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thumbnail_analysis_profile_id')
                ->constrained('thumbnail_analysis_profiles', 'id', 'thumb_items_profile_fk')
                ->cascadeOnDelete();
            $table->foreignId('analyzer_run_video_id')
                ->constrained('analyzer_run_videos', 'id', 'thumb_items_membership_fk')
                ->cascadeOnDelete();
            $table->foreignId('video_id')->constrained()->restrictOnDelete();
            $table->foreignId('source_item_id')->nullable()
                ->constrained('thumbnail_analysis_items', 'id', 'thumb_items_source_fk')
                ->nullOnDelete();
            $table->string('role', 32);
            $table->string('status', 32);
            $table->string('cache_status', 16)->default('fresh');
            $table->text('source_url')->nullable();
            $table->char('source_url_hash', 64)->nullable();
            $table->char('source_checksum', 64)->nullable();
            $table->string('mime_type', 64)->nullable();
            $table->unsignedInteger('byte_count')->nullable();
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            $table->decimal('aspect_ratio', 8, 4)->nullable();
            $table->decimal('average_brightness', 7, 4)->nullable();
            $table->decimal('average_saturation', 7, 4)->nullable();
            $table->decimal('contrast_score', 7, 4)->nullable();
            $table->decimal('edge_density', 7, 4)->nullable();
            $table->string('dominant_color', 32)->nullable();
            $table->string('brightness_class', 32)->nullable();
            $table->string('saturation_class', 32)->nullable();
            $table->string('contrast_class', 32)->nullable();
            $table->string('composition_class', 32)->nullable();
            $table->string('cluster_key', 160)->nullable();
            $table->decimal('confidence_score', 7, 4)->nullable();
            $table->string('error_code', 64)->nullable();
            $table->timestamp('analyzed_at')->nullable();
            $table->timestamps();

            $table->unique(['thumbnail_analysis_profile_id', 'analyzer_run_video_id'], 'thumb_items_profile_membership_unique');
            $table->index(['video_id', 'source_url_hash', 'status', 'analyzed_at'], 'thumb_items_video_cache_index');
        });

        Schema::create('thumbnail_performance_aggregates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thumbnail_analysis_profile_id')
                ->constrained('thumbnail_analysis_profiles', 'id', 'thumb_aggs_profile_fk')
                ->cascadeOnDelete();
            $table->string('cluster_key', 160);
            $table->string('label', 255);
            $table->unsignedSmallInteger('position');
            $table->boolean('meets_minimum_sample');
            $table->unsignedSmallInteger('sample_count');
            $table->unsignedSmallInteger('view_sample_count');
            $table->decimal('median_views', 20, 4)->nullable();
            $table->decimal('average_views', 20, 4)->nullable();
            $table->unsignedSmallInteger('views_per_day_sample_count');
            $table->decimal('median_views_per_day', 20, 6)->nullable();
            $table->decimal('average_views_per_day', 20, 6)->nullable();
            $table->unsignedSmallInteger('breakout_sample_count');
            $table->unsignedSmallInteger('breakout_count');
            $table->decimal('breakout_rate_percent', 7, 4)->nullable();
            $table->json('evidence_video_ids');
            $table->timestamps();

            $table->unique(['thumbnail_analysis_profile_id', 'cluster_key'], 'thumb_aggs_profile_cluster_unique');
            $table->index(['thumbnail_analysis_profile_id', 'position'], 'thumb_aggs_profile_position_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('thumbnail_performance_aggregates');
        Schema::dropIfExists('thumbnail_analysis_items');
        Schema::dropIfExists('thumbnail_analysis_profiles');
    }
};
