<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profitability_fit_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('research_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('opportunity_score_id')->constrained()->cascadeOnDelete();
            $table->string('formula_version', 64);
            $table->decimal('fit_score', 7, 4);
            $table->decimal('confidence_score', 7, 4);
            $table->json('input_summary');
            $table->json('explanations');
            $table->json('warnings');
            $table->timestamp('calculated_at');
            $table->timestamps();

            $table->unique(['research_run_id', 'formula_version'], 'profitability_fit_scores_run_version_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profitability_fit_scores');
    }
};
