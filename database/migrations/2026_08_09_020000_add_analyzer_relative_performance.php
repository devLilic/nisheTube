<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('analyzer_runs', 'threshold_version')) {
            Schema::table('analyzer_runs', function (Blueprint $table) {
                $table->string('threshold_version', 64)->default('video-relative-performance-v1')->after('calculation_version');
            });
        }

        if (! Schema::hasColumn('video_analysis_metrics', 'channel_median_ratio')) {
            Schema::table('video_analysis_metrics', function (Blueprint $table) {
                $table->decimal('channel_median_ratio', 20, 8)->nullable()->after('views_to_subscribers_ratio');
                $table->decimal('channel_average_ratio', 20, 8)->nullable()->after('channel_median_ratio');
                $table->unsignedSmallInteger('recent_rank')->nullable()->after('channel_average_ratio');
                $table->decimal('recent_percentile', 8, 4)->nullable()->after('recent_rank');
                $table->unsignedSmallInteger('recent_comparison_count')->default(0)->after('recent_percentile');
                $table->string('breakout_class', 32)->nullable()->after('recent_comparison_count');
                $table->string('threshold_version', 64)->nullable()->after('breakout_class');
            });
        }

        if (! Schema::hasColumn('channel_analysis_metrics', 'strong_count')) {
            Schema::table('channel_analysis_metrics', function (Blueprint $table) {
                $table->unsignedSmallInteger('strong_count')->nullable()->after('category_distribution');
                $table->decimal('strong_share_percent', 8, 4)->nullable()->after('strong_count');
                $table->unsignedSmallInteger('breakout_count')->nullable()->after('strong_share_percent');
                $table->decimal('breakout_share_percent', 8, 4)->nullable()->after('breakout_count');
                $table->string('threshold_version', 64)->nullable()->after('breakout_share_percent');
            });
        }

        if (! Schema::hasColumn('analyzer_run_videos', 'channel_median_ratio')) {
            Schema::table('analyzer_run_videos', function (Blueprint $table) {
                $table->decimal('channel_median_ratio', 20, 8)->nullable()->after('source_position');
                $table->string('breakout_class', 32)->nullable()->after('channel_median_ratio');
                $table->string('threshold_version', 64)->nullable()->after('breakout_class');
                $table->index(['analyzer_run_id', 'role', 'breakout_class'], 'analyzer_run_videos_outlier_index');
            });
        }
    }

    public function down(): void
    {
        Schema::table('analyzer_run_videos', function (Blueprint $table) {
            $table->dropIndex('analyzer_run_videos_outlier_index');
            $table->dropColumn(['channel_median_ratio', 'breakout_class', 'threshold_version']);
        });

        Schema::table('channel_analysis_metrics', function (Blueprint $table) {
            $table->dropColumn([
                'strong_count',
                'strong_share_percent',
                'breakout_count',
                'breakout_share_percent',
                'threshold_version',
            ]);
        });

        Schema::table('video_analysis_metrics', function (Blueprint $table) {
            $table->dropColumn([
                'channel_median_ratio',
                'channel_average_ratio',
                'recent_rank',
                'recent_percentile',
                'recent_comparison_count',
                'breakout_class',
                'threshold_version',
            ]);
        });

        Schema::table('analyzer_runs', function (Blueprint $table) {
            $table->dropColumn('threshold_version');
        });
    }
};
