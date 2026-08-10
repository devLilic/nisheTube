<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('research_run_videos', function (Blueprint $table) {
            $table->index(['video_id', 'research_run_id'], 'research_run_videos_video_run_index');
        });
        Schema::table('analyzer_runs', function (Blueprint $table) {
            $table->index(['user_id', 'video_id', 'completed_at'], 'analyzer_runs_owner_video_completed_index');
            $table->index(['user_id', 'channel_id', 'completed_at'], 'analyzer_runs_owner_channel_completed_index');
        });
    }

    public function down(): void
    {
        Schema::table('research_run_videos', fn (Blueprint $table) => $table->dropIndex('research_run_videos_video_run_index'));
        Schema::table('analyzer_runs', function (Blueprint $table) {
            $table->dropIndex('analyzer_runs_owner_video_completed_index');
            $table->dropIndex('analyzer_runs_owner_channel_completed_index');
        });
    }
};
