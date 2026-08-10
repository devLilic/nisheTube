<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('video_snapshots', function (Blueprint $table) {
            $table->unsignedBigInteger('research_run_id')->nullable()->change();
        });

        Schema::table('channel_snapshots', function (Blueprint $table) {
            $table->unsignedBigInteger('research_run_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (
            DB::table('video_snapshots')->whereNull('research_run_id')->exists()
            || DB::table('channel_snapshots')->whereNull('research_run_id')->exists()
        ) {
            throw new RuntimeException(
                'Shared observations without a legacy Research owner must be retained before this migration can be rolled back.',
            );
        }

        Schema::table('video_snapshots', function (Blueprint $table) {
            $table->unsignedBigInteger('research_run_id')->nullable(false)->change();
        });

        Schema::table('channel_snapshots', function (Blueprint $table) {
            $table->unsignedBigInteger('research_run_id')->nullable(false)->change();
        });
    }
};
