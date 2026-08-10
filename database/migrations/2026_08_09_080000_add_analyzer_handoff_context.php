<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analyzer_runs', function (Blueprint $table) {
            $table->json('navigation_context')->nullable()->after('origin_reference');
        });
    }

    public function down(): void
    {
        Schema::table('analyzer_runs', function (Blueprint $table) {
            $table->dropColumn('navigation_context');
        });
    }
};
