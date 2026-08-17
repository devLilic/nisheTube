<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('research_evidence_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('research_run_id')->constrained()->cascadeOnDelete();
            $table->string('evidence_version', 64);
            $table->string('normalization_version', 64);
            $table->unsignedInteger('full_sample_count');
            $table->unsignedInteger('strict_sample_count');
            $table->json('input_summary');
            $table->json('sample_evidence');
            $table->json('format_evidence');
            $table->json('outlier_evidence');
            $table->json('stability_evidence');
            $table->json('warnings')->nullable();
            $table->timestamp('calculated_at');
            $table->timestamps();

            $table->unique(['research_run_id', 'evidence_version'], 'research_evidence_run_version_unique');
        });

        Schema::create('research_result_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('research_evidence_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('research_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('video_id')->constrained()->restrictOnDelete();
            $table->foreignId('channel_id')->constrained()->restrictOnDelete();
            $table->foreignId('video_snapshot_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('channel_snapshot_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('relevance_class', 32);
            $table->decimal('relevance_score', 7, 4);
            $table->string('format_class', 16);
            $table->json('signals');
            $table->json('metric_inputs');
            $table->timestamps();

            $table->unique(['research_evidence_profile_id', 'video_id'], 'research_result_evidence_profile_video_unique');
            $table->index(['research_run_id', 'relevance_class', 'video_id'], 'research_result_evidence_run_class_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('research_result_evidence');
        Schema::dropIfExists('research_evidence_profiles');
    }
};
