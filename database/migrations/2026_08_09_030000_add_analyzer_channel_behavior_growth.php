<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analyzer_runs', function (Blueprint $table) {
            $table->string('behavior_version', 64)->nullable()->after('threshold_version');
        });

        Schema::table('video_analysis_metrics', function (Blueprint $table) {
            $table->foreignId('previous_video_snapshot_id')->nullable()->after('threshold_version')
                ->constrained('video_snapshots')->nullOnDelete();
            $table->unsignedBigInteger('observed_elapsed_seconds')->nullable()->after('previous_video_snapshot_id');
            $table->bigInteger('observed_view_delta')->nullable()->after('observed_elapsed_seconds');
            $table->bigInteger('observed_like_delta')->nullable()->after('observed_view_delta');
            $table->bigInteger('observed_comment_delta')->nullable()->after('observed_like_delta');
            $table->decimal('observed_recent_views_per_day', 20, 6)->nullable()->after('observed_comment_delta');
            $table->decimal('observed_view_growth_percent', 20, 6)->nullable()->after('observed_recent_views_per_day');
            $table->string('behavior_version', 64)->nullable()->after('observed_view_growth_percent');
        });

        Schema::table('channel_analysis_metrics', function (Blueprint $table) {
            $table->unsignedSmallInteger('momentum_recent_count')->default(0)->after('threshold_version');
            $table->unsignedSmallInteger('momentum_previous_count')->default(0)->after('momentum_recent_count');
            $table->decimal('momentum_recent_median_views_per_day', 20, 6)->nullable()->after('momentum_previous_count');
            $table->decimal('momentum_previous_median_views_per_day', 20, 6)->nullable()->after('momentum_recent_median_views_per_day');
            $table->decimal('momentum_ratio', 20, 8)->nullable()->after('momentum_previous_median_views_per_day');
            $table->string('momentum_class', 32)->nullable()->after('momentum_ratio');
            $table->unsignedSmallInteger('consistency_sample_count')->default(0)->after('momentum_class');
            $table->decimal('consistency_score', 8, 4)->nullable()->after('consistency_sample_count');
            $table->string('consistency_class', 32)->nullable()->after('consistency_score');
            $table->unsignedSmallInteger('duration_performance_sample_count')->default(0)->after('consistency_class');
            $table->decimal('duration_performance_correlation', 10, 8)->nullable()->after('duration_performance_sample_count');
            $table->string('duration_performance_class', 48)->nullable()->after('duration_performance_correlation');
            $table->json('duration_performance_buckets')->nullable()->after('duration_performance_class');
            $table->foreignId('previous_channel_snapshot_id')->nullable()->after('duration_performance_buckets')
                ->constrained('channel_snapshots')->nullOnDelete();
            $table->unsignedBigInteger('observed_elapsed_seconds')->nullable()->after('previous_channel_snapshot_id');
            $table->bigInteger('observed_view_delta')->nullable()->after('observed_elapsed_seconds');
            $table->bigInteger('observed_subscriber_delta')->nullable()->after('observed_view_delta');
            $table->bigInteger('observed_video_delta')->nullable()->after('observed_subscriber_delta');
            $table->decimal('observed_view_growth_percent', 20, 6)->nullable()->after('observed_video_delta');
            $table->string('behavior_version', 64)->nullable()->after('observed_view_growth_percent');
        });
    }

    public function down(): void
    {
        Schema::table('channel_analysis_metrics', function (Blueprint $table) {
            $table->dropForeign(['previous_channel_snapshot_id']);
            $table->dropColumn([
                'momentum_recent_count', 'momentum_previous_count', 'momentum_recent_median_views_per_day',
                'momentum_previous_median_views_per_day', 'momentum_ratio', 'momentum_class',
                'consistency_sample_count', 'consistency_score', 'consistency_class',
                'duration_performance_sample_count', 'duration_performance_correlation',
                'duration_performance_class', 'duration_performance_buckets', 'previous_channel_snapshot_id',
                'observed_elapsed_seconds', 'observed_view_delta', 'observed_subscriber_delta',
                'observed_video_delta', 'observed_view_growth_percent', 'behavior_version',
            ]);
        });

        Schema::table('video_analysis_metrics', function (Blueprint $table) {
            $table->dropForeign(['previous_video_snapshot_id']);
            $table->dropColumn([
                'previous_video_snapshot_id', 'observed_elapsed_seconds', 'observed_view_delta',
                'observed_like_delta', 'observed_comment_delta', 'observed_recent_views_per_day',
                'observed_view_growth_percent', 'behavior_version',
            ]);
        });

        Schema::table('analyzer_runs', function (Blueprint $table) {
            $table->dropColumn('behavior_version');
        });
    }
};
