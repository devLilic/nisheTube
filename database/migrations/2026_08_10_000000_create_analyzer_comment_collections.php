<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comment_collection_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('analyzer_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('video_id')->constrained()->restrictOnDelete();
            $table->string('provider', 32)->default('youtube');
            $table->string('provider_video_id', 64);
            $table->string('status', 32);
            $table->unsignedSmallInteger('max_comments');
            $table->unsignedSmallInteger('page_size');
            $table->string('next_page_token')->nullable();
            $table->unsignedSmallInteger('pages_collected')->default(0);
            $table->unsignedInteger('comments_collected')->default(0);
            $table->unsignedInteger('reported_total_results')->nullable();
            $table->string('reply_scope', 32)->default('top_level_only');
            $table->string('error_code', 100)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('collected_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at'], 'comment_runs_owner_created_index');
            $table->index(['user_id', 'status'], 'comment_runs_owner_status_index');
            $table->index(['analyzer_run_id', 'created_at'], 'comment_runs_analyzer_created_index');
            $table->index(['user_id', 'completed_at'], 'comment_runs_owner_completed_index');
        });

        Schema::create('public_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comment_collection_run_id')->constrained()->cascadeOnDelete();
            $table->string('provider_comment_id', 128);
            $table->text('text');
            $table->unsignedBigInteger('like_count')->nullable();
            $table->unsignedInteger('reply_count')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('provider_updated_at')->nullable();
            $table->timestamps();

            $table->unique(['comment_collection_run_id', 'provider_comment_id'], 'public_comments_run_provider_unique');
            $table->index(['comment_collection_run_id', 'like_count'], 'public_comments_run_likes_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_comments');
        Schema::dropIfExists('comment_collection_runs');
    }
};
