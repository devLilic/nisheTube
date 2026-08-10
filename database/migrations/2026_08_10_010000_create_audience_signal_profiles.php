<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audience_signal_profiles', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('analyzer_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('comment_collection_run_id')->constrained()->cascadeOnDelete();
            $table->string('status', 32);
            $table->string('provenance', 32)->default('inferred');
            $table->string('provider', 64);
            $table->string('algorithm_version', 64);
            $table->string('language', 8)->default('und');
            $table->unsignedSmallInteger('comment_count');
            $table->unsignedSmallInteger('usable_comment_count');
            $table->decimal('confidence_score', 7, 4)->nullable();
            $table->json('warnings')->nullable();
            $table->timestamp('calculated_at');
            $table->timestamps();

            $table->unique(
                ['comment_collection_run_id', 'provider', 'algorithm_version'],
                'audience_profiles_collection_provider_version_unique',
            );
            $table->index(['user_id', 'analyzer_run_id'], 'audience_profiles_owner_analyzer_index');
        });

        Schema::create('audience_signals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audience_signal_profile_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 32);
            $table->string('label', 255);
            $table->string('label_key', 255);
            $table->decimal('confidence', 7, 4);
            $table->unsignedSmallInteger('comment_count');
            $table->unsignedSmallInteger('occurrence_count');
            $table->unsignedSmallInteger('position');
            $table->timestamps();

            $table->unique(
                ['audience_signal_profile_id', 'kind', 'label_key'],
                'audience_signals_profile_kind_key_unique',
            );
            $table->index(['audience_signal_profile_id', 'kind', 'position'], 'audience_signals_profile_kind_position_index');
        });

        Schema::create('audience_signal_evidence', function (Blueprint $table) {
            $table->foreignId('audience_signal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('public_comment_id')->constrained()->cascadeOnDelete();
            $table->primary(['audience_signal_id', 'public_comment_id'], 'audience_signal_evidence_primary');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audience_signal_evidence');
        Schema::dropIfExists('audience_signals');
        Schema::dropIfExists('audience_signal_profiles');
    }
};
