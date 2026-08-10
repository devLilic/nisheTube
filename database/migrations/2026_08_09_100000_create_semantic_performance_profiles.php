<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('semantic_performance_profiles', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('analyzer_run_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('semantic_topic_profile_id')->nullable()->unique()->constrained()->cascadeOnDelete();
            $table->string('status', 24);
            $table->string('provenance', 24)->default('inferred');
            $table->string('calculation_version', 64);
            $table->string('topic_version', 64)->nullable();
            $table->string('title_pattern_version', 64);
            $table->unsignedSmallInteger('minimum_sample_size');
            $table->unsignedSmallInteger('cohort_video_count');
            $table->json('warnings')->nullable();
            $table->timestamp('calculated_at');
            $table->timestamps();

            $table->index(['user_id', 'calculated_at'], 'semantic_performance_owner_calculated_index');
        });

        Schema::create('semantic_performance_aggregates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('semantic_performance_profile_id')
                ->constrained(table: 'semantic_performance_profiles', indexName: 'semantic_performance_aggregate_profile_fk')
                ->cascadeOnDelete();
            $table->string('group_type', 32);
            $table->string('label');
            $table->string('label_key');
            $table->boolean('is_unclassified')->default(false);
            $table->boolean('meets_minimum_sample')->default(false);
            $table->unsignedSmallInteger('position');
            $table->unsignedSmallInteger('sample_count');
            $table->unsignedSmallInteger('view_sample_count');
            $table->decimal('median_views', 20, 4)->nullable();
            $table->decimal('average_views', 20, 4)->nullable();
            $table->unsignedSmallInteger('views_per_day_sample_count');
            $table->decimal('median_views_per_day', 20, 6)->nullable();
            $table->decimal('average_views_per_day', 20, 6)->nullable();
            $table->unsignedSmallInteger('breakout_sample_count');
            $table->unsignedSmallInteger('breakout_count');
            $table->decimal('breakout_rate_percent', 8, 4)->nullable();
            $table->json('evidence_video_ids');
            $table->timestamps();

            $table->unique(['semantic_performance_profile_id', 'group_type', 'label_key'], 'semantic_performance_profile_group_key_unique');
            $table->index(['group_type', 'label_key'], 'semantic_performance_group_key_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('semantic_performance_aggregates');
        Schema::dropIfExists('semantic_performance_profiles');
    }
};
