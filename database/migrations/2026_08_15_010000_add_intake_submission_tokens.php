<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('research_runs', function (Blueprint $table) {
            $table->uuid('submission_token')->nullable()->after('public_id');
            $table->unique(['user_id', 'submission_token'], 'research_runs_owner_submission_unique');
        });

        Schema::table('discovery_runs', function (Blueprint $table) {
            $table->uuid('submission_token')->nullable()->after('public_id');
            $table->unique(['user_id', 'submission_token'], 'discovery_runs_owner_submission_unique');
        });
    }

    public function down(): void
    {
        Schema::table('discovery_runs', function (Blueprint $table) {
            $table->dropUnique('discovery_runs_owner_submission_unique');
            $table->dropColumn('submission_token');
        });

        Schema::table('research_runs', function (Blueprint $table) {
            $table->dropUnique('research_runs_owner_submission_unique');
            $table->dropColumn('submission_token');
        });
    }
};
