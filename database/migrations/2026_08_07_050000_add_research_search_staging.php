<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('research_runs', 'collection_warnings')) {
            Schema::table('research_runs', function (Blueprint $table) {
                $table->json('collection_warnings')->nullable()->after('progress_percent');
            });
        }

        if (! Schema::hasTable('research_run_search_pages')) {
            Schema::create('research_run_search_pages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('research_run_id')->constrained()->cascadeOnDelete();
                $table->unsignedSmallInteger('page_number');
                $table->string('request_page_token', 255)->nullable();
                $table->string('next_page_token', 255)->nullable();
                $table->unsignedSmallInteger('result_count');
                $table->unsignedInteger('approximate_total_results')->nullable();
                $table->json('warnings')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasIndex('research_run_search_pages', ['research_run_id', 'page_number'], 'unique')) {
            Schema::table('research_run_search_pages', function (Blueprint $table) {
                $table->unique(['research_run_id', 'page_number'], 'rr_search_pages_run_page_unique');
            });
        }

        if (! Schema::hasTable('research_run_search_results')) {
            Schema::create('research_run_search_results', function (Blueprint $table) {
                $table->id();
                $table->foreignId('research_run_id')->constrained()->cascadeOnDelete();
                $table->string('provider_video_id', 255);
                $table->string('provider_channel_id', 255);
                $table->text('title');
                $table->timestamp('published_at');
                $table->unsignedInteger('result_rank');
                $table->unsignedSmallInteger('page_number');
                $table->unsignedSmallInteger('provider_order');
                $table->timestamps();
            });
        }

        if (! Schema::hasIndex('research_run_search_results', ['research_run_id', 'provider_video_id'], 'unique')) {
            Schema::table('research_run_search_results', function (Blueprint $table) {
                $table->unique(['research_run_id', 'provider_video_id'], 'rr_search_results_run_video_unique');
            });
        }

        if (! Schema::hasIndex('research_run_search_results', ['research_run_id', 'result_rank'])) {
            Schema::table('research_run_search_results', function (Blueprint $table) {
                $table->index(['research_run_id', 'result_rank'], 'rr_search_results_run_rank_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('research_run_search_results');
        Schema::dropIfExists('research_run_search_pages');

        if (Schema::hasColumn('research_runs', 'collection_warnings')) {
            Schema::table('research_runs', function (Blueprint $table) {
                $table->dropColumn('collection_warnings');
            });
        }
    }
};
