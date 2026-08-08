<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channels', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 32);
            $table->string('provider_channel_id', 255);
            $table->text('title');
            $table->string('custom_url', 255)->nullable();
            $table->text('thumbnail_url')->nullable();
            $table->char('country', 2)->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_channel_id'], 'channels_provider_id_unique');
        });

        Schema::create('videos', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 32);
            $table->string('provider_video_id', 255);
            $table->foreignId('channel_id')->constrained()->restrictOnDelete();
            $table->text('title');
            $table->text('thumbnail_url')->nullable();
            $table->timestamp('published_at');
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->string('category_id', 32)->nullable();
            $table->boolean('is_short')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_video_id'], 'videos_provider_id_unique');
            $table->index(['channel_id', 'published_at'], 'videos_channel_published_index');
        });

        Schema::create('research_run_videos', function (Blueprint $table) {
            $table->foreignId('research_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('video_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('result_rank');
            $table->unsignedSmallInteger('page_number');
            $table->unsignedSmallInteger('provider_order');
            $table->json('matched_query_metadata')->nullable();
            $table->timestamps();

            $table->unique(['research_run_id', 'video_id'], 'rr_videos_run_video_unique');
            $table->index(['research_run_id', 'result_rank'], 'rr_videos_run_rank_index');
        });

        Schema::create('channel_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_id')->constrained()->restrictOnDelete();
            $table->foreignId('research_run_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('subscriber_count')->nullable();
            $table->unsignedBigInteger('view_count')->nullable();
            $table->unsignedBigInteger('video_count')->nullable();
            $table->boolean('subscriber_count_hidden')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamp('collected_at');
            $table->timestamps();

            $table->unique(['research_run_id', 'channel_id'], 'channel_snapshots_run_channel_unique');
            $table->index(['channel_id', 'collected_at'], 'channel_snapshots_channel_collected_index');
        });

        Schema::create('video_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('video_id')->constrained()->restrictOnDelete();
            $table->foreignId('research_run_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('view_count')->nullable();
            $table->unsignedBigInteger('like_count')->nullable();
            $table->unsignedBigInteger('comment_count')->nullable();
            $table->unsignedBigInteger('age_seconds')->nullable();
            $table->decimal('views_per_day', 20, 6)->nullable();
            $table->decimal('views_to_subscribers_ratio', 20, 8)->nullable();
            $table->timestamp('collected_at');
            $table->timestamps();

            $table->unique(['research_run_id', 'video_id'], 'video_snapshots_run_video_unique');
            $table->index(['video_id', 'collected_at'], 'video_snapshots_video_collected_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_snapshots');
        Schema::dropIfExists('channel_snapshots');
        Schema::dropIfExists('research_run_videos');
        Schema::dropIfExists('videos');
        Schema::dropIfExists('channels');
    }
};
