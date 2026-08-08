<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discovery_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('research_project_id')->nullable()->constrained('research_projects')->nullOnDelete();
            $table->foreignId('market_id')->constrained()->restrictOnDelete();
            $table->string('status', 32);
            $table->string('market_key', 32);
            $table->char('region_code', 2)->nullable();
            $table->string('relevance_language', 8);
            $table->json('parameters');
            $table->unsignedSmallInteger('seed_count')->default(0);
            $table->unsignedInteger('candidate_count')->default(0);
            $table->unsignedTinyInteger('progress_percent')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('error_code', 64)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('discovery_seeds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('discovery_run_id')->constrained()->cascadeOnDelete();
            $table->string('seed_query', 500);
            $table->string('seed_key', 500);
            $table->string('source', 32);
            $table->foreignId('research_run_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['discovery_run_id', 'seed_key'], 'discovery_seeds_run_key_unique');
            $table->index(['research_run_id', 'discovery_run_id']);
        });

        Schema::create('niche_candidates', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('discovery_run_id')->constrained()->cascadeOnDelete();
            $table->string('phrase', 500);
            $table->string('phrase_key', 500);
            $table->string('cluster_key', 64);
            $table->text('summary');
            $table->json('evidence');
            $table->decimal('overall_score', 7, 4)->nullable();
            $table->decimal('confidence_score', 7, 4)->nullable();
            $table->string('formula_version', 64)->nullable();
            $table->string('status', 32);
            $table->foreignId('validation_research_run_id')->nullable()->constrained('research_runs')->nullOnDelete();
            $table->timestamps();

            $table->unique(['discovery_run_id', 'phrase_key'], 'niche_candidates_run_phrase_unique');
            $table->index(['discovery_run_id', 'status', 'overall_score'], 'niche_candidates_run_status_score_index');
            $table->index(['validation_research_run_id', 'discovery_run_id'], 'niche_candidates_validation_run_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('niche_candidates');
        Schema::dropIfExists('discovery_seeds');
        Schema::dropIfExists('discovery_runs');
    }
};
