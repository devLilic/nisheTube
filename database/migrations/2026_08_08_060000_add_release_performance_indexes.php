<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('research_runs', function (Blueprint $table): void {
            $table->index(['user_id', 'status', 'completed_at'], 'research_runs_owner_status_completed_index');
            $table->index(['user_id', 'status', 'failed_at'], 'research_runs_owner_status_failed_index');
        });

        Schema::table('favorites', function (Blueprint $table): void {
            $table->index(['user_id', 'updated_at'], 'favorites_owner_updated_index');
            $table->index(['user_id', 'target_type', 'updated_at'], 'favorites_owner_type_updated_index');
        });

        Schema::table('opportunity_scores', function (Blueprint $table): void {
            $table->index(
                ['formula_version', 'calculated_at', 'overall_score'],
                'opportunity_scores_formula_calculated_score_index',
            );
        });

        Schema::table('exports', function (Blueprint $table): void {
            $table->index(['user_id', 'created_at'], 'exports_owner_created_index');
        });
    }

    public function down(): void
    {
        Schema::table('research_runs', function (Blueprint $table): void {
            $table->dropIndex('research_runs_owner_status_completed_index');
            $table->dropIndex('research_runs_owner_status_failed_index');
        });

        Schema::table('favorites', function (Blueprint $table): void {
            $table->dropIndex('favorites_owner_updated_index');
            $table->dropIndex('favorites_owner_type_updated_index');
        });

        Schema::table('opportunity_scores', function (Blueprint $table): void {
            $table->dropIndex('opportunity_scores_formula_calculated_score_index');
        });

        Schema::table('exports', function (Blueprint $table): void {
            $table->dropIndex('exports_owner_created_index');
        });
    }
};
