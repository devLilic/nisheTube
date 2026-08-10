<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analyzer_runs', function (Blueprint $table) {
            $table->foreignId('channel_snapshot_id')->nullable()->after('channel_id')
                ->constrained()->restrictOnDelete();
            $table->index(
                ['user_id', 'target_kind', 'target_provider_id', 'created_at'],
                'analyzer_runs_owner_kind_target_index',
            );
        });

        Schema::create('analyzer_curations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('subject_type', 16);
            $table->unsignedBigInteger('subject_id');
            $table->string('research_status', 32)->default('unreviewed');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'subject_type', 'subject_id'], 'analyzer_curations_owner_subject_unique');
            $table->index(['user_id', 'research_status', 'updated_at'], 'analyzer_curations_owner_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analyzer_curations');

        Schema::table('analyzer_runs', function (Blueprint $table) {
            $table->dropIndex('analyzer_runs_owner_kind_target_index');
            $table->dropConstrainedForeignId('channel_snapshot_id');
        });
    }
};
