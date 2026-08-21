<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('research_projects', function (Blueprint $table): void {
            $table->text('purpose')->nullable()->after('description');
            $table->string('market_key', 32)->nullable()->after('purpose');
            $table->json('themes')->nullable()->after('market_key');
            $table->string('decision_status', 24)->default('exploring')->after('themes');
            $table->text('decision_note')->nullable()->after('decision_status');
            $table->index(['user_id', 'decision_status', 'updated_at'], 'project_owner_decision_state_index');
        });

        Schema::table('saved_comment_ideas', function (Blueprint $table): void {
            $table->foreignId('topic_workspace_id')->nullable()->after('video_id')->constrained()->nullOnDelete();
            $table->foreignId('niche_candidate_id')->nullable()->after('topic_workspace_id')->constrained()->nullOnDelete();
            $table->string('decision_status', 24)->default('new')->after('source_published_at');
            $table->string('format', 120)->nullable()->after('decision_status');
            $table->string('audience', 240)->nullable()->after('format');
            $table->text('decision_note')->nullable()->after('audience');
            $table->index(['user_id', 'decision_status', 'updated_at'], 'idea_owner_decision_state_index');
        });
    }

    public function down(): void
    {
        Schema::table('saved_comment_ideas', function (Blueprint $table): void {
            $table->dropIndex('idea_owner_decision_state_index');
            $table->dropForeign(['topic_workspace_id']);
            $table->dropForeign(['niche_candidate_id']);
            $table->dropColumn(['topic_workspace_id', 'niche_candidate_id', 'decision_status', 'format', 'audience', 'decision_note']);
        });

        Schema::table('research_projects', function (Blueprint $table): void {
            $table->dropIndex('project_owner_decision_state_index');
            $table->dropColumn(['purpose', 'market_key', 'themes', 'decision_status', 'decision_note']);
        });
    }
};
