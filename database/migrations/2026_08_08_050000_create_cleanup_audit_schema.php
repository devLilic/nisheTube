<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cleanup_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('initiated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('mode', 32);
            $table->string('status', 32);
            $table->timestamp('cutoff_at');
            $table->boolean('dry_run')->default(false);
            $table->json('eligible_counts');
            $table->json('deleted_counts');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('error_code', 64)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at'], 'cleanup_runs_owner_created_index');
            $table->index(['status', 'created_at'], 'cleanup_runs_status_created_index');
        });

        Schema::create('snapshot_deletion_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cleanup_run_id')->constrained()->cascadeOnDelete();
            $table->string('target_type', 32);
            $table->string('target_reference', 255);
            $table->timestamp('original_collection_at');
            $table->string('outcome', 32);
            $table->boolean('favorite_impacted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['cleanup_run_id', 'target_type', 'target_reference'],
                'snapshot_deletion_items_run_target_unique',
            );
            $table->index(['target_type', 'target_reference'], 'snapshot_deletion_items_target_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('snapshot_deletion_items');
        Schema::dropIfExists('cleanup_runs');
    }
};
