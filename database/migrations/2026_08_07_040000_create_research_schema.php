<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('research_projects', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->string('color', 16)->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'updated_at']);
        });

        Schema::create('research_queries', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('research_project_id')->nullable()->constrained('research_projects')->nullOnDelete();
            $table->foreignId('market_id')->constrained()->restrictOnDelete();
            $table->string('query_text', 500);
            $table->string('query_key', 500);
            $table->string('search_order', 32)->default('relevance');
            $table->timestamp('published_after')->nullable();
            $table->timestamp('published_before')->nullable();
            $table->string('video_duration', 16)->nullable();
            $table->string('video_category_id', 32)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'updated_at']);
            $table->index(['user_id', 'market_id', 'query_key'], 'research_queries_owner_market_key_index');
        });

        Schema::create('research_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('research_query_id')->constrained()->restrictOnDelete();
            $table->string('kind', 32);
            $table->string('status', 32);
            $table->unsignedSmallInteger('attempt_number');
            $table->string('query_text', 500);
            $table->string('market_key', 32);
            $table->char('region_code', 2)->nullable();
            $table->string('relevance_language', 8);
            $table->json('parameters');
            $table->unsignedInteger('requested_result_count');
            $table->unsignedInteger('collected_result_count')->default(0);
            $table->unsignedInteger('enriched_result_count')->default(0);
            $table->unsignedTinyInteger('progress_percent')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('search_completed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('error_code', 64)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['research_query_id', 'attempt_number']);
            $table->index(['user_id', 'created_at']);
            $table->index(['research_query_id', 'completed_at']);
            $table->index(['status', 'created_at']);
        });

        Schema::table('api_usage_events', function (Blueprint $table) {
            $table->foreign('research_run_id')
                ->references('id')
                ->on('research_runs')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('api_usage_events', function (Blueprint $table) {
            $table->dropForeign(['research_run_id']);
        });

        Schema::dropIfExists('research_runs');
        Schema::dropIfExists('research_queries');
        Schema::dropIfExists('research_projects');
    }
};
