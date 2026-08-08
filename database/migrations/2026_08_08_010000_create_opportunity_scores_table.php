<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opportunity_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('research_run_id')->constrained()->cascadeOnDelete();
            $table->string('formula_version', 64);
            $table->decimal('overall_score', 7, 4);
            $table->decimal('demand_momentum_score', 7, 4);
            $table->decimal('competition_opportunity_score', 7, 4);
            $table->decimal('audience_reachability_score', 7, 4);
            $table->decimal('content_freshness_gap_score', 7, 4);
            $table->decimal('creator_viability_score', 7, 4);
            $table->decimal('confidence_score', 7, 4);
            $table->unsignedInteger('sample_size');
            $table->json('input_summary');
            $table->json('explanations');
            $table->json('warnings');
            $table->timestamp('calculated_at');
            $table->timestamps();

            $table->unique(['research_run_id', 'formula_version'], 'opportunity_scores_run_version_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opportunity_scores');
    }
};
