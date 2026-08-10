<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('watchlist_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('target_type', 16);
            $table->unsignedBigInteger('target_id');
            $table->foreignId('research_project_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('topic_workspace_id')->nullable();
            $table->string('status', 32)->default('monitoring');
            $table->boolean('is_active')->default(true);
            $table->string('refresh_mode', 16)->default('manual');
            $table->text('note')->nullable();
            $table->timestamp('last_observed_at')->nullable();
            $table->timestamp('last_refreshed_at')->nullable();
            $table->timestamp('next_refresh_at')->nullable();
            $table->unsignedBigInteger('last_refresh_run_id')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'target_type', 'target_id'], 'watchlist_owner_target_unique');
            $table->index(['user_id', 'is_active', 'status', 'updated_at'], 'watchlist_owner_state_index');
            $table->index(['user_id', 'next_refresh_at'], 'watchlist_owner_next_refresh_index');
            $table->index('topic_workspace_id');
        });

        Schema::create('watchlist_refresh_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('watchlist_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('analyzer_run_id')->constrained()->restrictOnDelete();
            $table->foreignId('collection_run_id')->constrained()->restrictOnDelete();
            $table->string('status', 24)->default('queued');
            $table->unsignedSmallInteger('attempt_number');
            $table->unsignedTinyInteger('progress_percent')->default(0);
            $table->json('warnings')->nullable();
            $table->string('error_code', 64)->nullable();
            $table->text('error_message')->nullable();
            $table->foreignId('previous_video_snapshot_id')->nullable()->constrained('video_snapshots')->restrictOnDelete();
            $table->foreignId('current_video_snapshot_id')->nullable()->constrained('video_snapshots')->restrictOnDelete();
            $table->foreignId('previous_channel_snapshot_id')->nullable()->constrained('channel_snapshots')->restrictOnDelete();
            $table->foreignId('current_channel_snapshot_id')->nullable()->constrained('channel_snapshots')->restrictOnDelete();
            $table->json('deltas')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->unique(['watchlist_item_id', 'attempt_number'], 'watchlist_refresh_attempt_unique');
            $table->index(['user_id', 'status', 'created_at'], 'watchlist_refresh_owner_state_index');
        });

        Schema::table('watchlist_items', function (Blueprint $table) {
            $table->foreign('last_refresh_run_id')->references('id')->on('watchlist_refresh_runs')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('watchlist_items', fn (Blueprint $table) => $table->dropForeign(['last_refresh_run_id']));
        Schema::dropIfExists('watchlist_refresh_runs');
        Schema::dropIfExists('watchlist_items');
    }
};
