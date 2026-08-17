<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('niche_candidates', function (Blueprint $table) {
            $table->string('evidence_state', 32)->default('legacy')->after('formula_version');
            $table->index(['discovery_run_id', 'evidence_state', 'overall_score'], 'niche_candidates_run_evidence_score_index');
        });
    }

    public function down(): void
    {
        Schema::table('niche_candidates', function (Blueprint $table) {
            $table->dropIndex('niche_candidates_run_evidence_score_index');
            $table->dropColumn('evidence_state');
        });
    }
};
