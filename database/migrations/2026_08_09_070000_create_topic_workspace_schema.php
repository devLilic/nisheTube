<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('topic_workspaces', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('research_project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('market_id')->constrained()->restrictOnDelete();
            $table->string('name', 160);
            $table->string('name_key', 160);
            $table->text('description')->nullable();
            $table->string('market_key', 32);
            $table->string('region_code', 8)->nullable();
            $table->string('relevance_language', 16);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'market_key', 'name_key'], 'topic_workspace_owner_market_name_unique');
            $table->index(['user_id', 'archived_at', 'updated_at'], 'topic_workspace_owner_state_index');
        });

        Schema::create('topic_workspace_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('topic_workspace_id')->constrained()->cascadeOnDelete();
            $table->string('target_type', 32);
            $table->unsignedBigInteger('target_id');
            $table->string('evidence_role', 32)->default('evidence');
            $table->text('note')->nullable();
            $table->unsignedInteger('sort_position')->default(0);
            $table->timestamps();

            $table->unique(['topic_workspace_id', 'target_type', 'target_id'], 'topic_workspace_target_unique');
            $table->index(['topic_workspace_id', 'evidence_role', 'sort_position'], 'topic_workspace_role_index');
        });

        Schema::create('topic_workspace_launches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('topic_workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('launch_type', 16);
            $table->foreignId('research_run_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('discovery_run_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->index(['topic_workspace_id', 'created_at'], 'topic_workspace_launch_history_index');
        });

        Schema::table('watchlist_items', function (Blueprint $table) {
            $table->foreign('topic_workspace_id')->references('id')->on('topic_workspaces')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('watchlist_items', fn (Blueprint $table) => $table->dropForeign(['topic_workspace_id']));
        Schema::dropIfExists('topic_workspace_launches');
        Schema::dropIfExists('topic_workspace_items');
        Schema::dropIfExists('topic_workspaces');
    }
};
