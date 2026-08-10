<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transcript_structure_profiles', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('analyzer_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transcript_document_id')->constrained()->cascadeOnDelete();
            $table->string('status', 32);
            $table->string('provenance', 32)->default('inferred');
            $table->string('provider', 64);
            $table->string('algorithm_version', 64);
            $table->string('language', 8)->default('und');
            $table->unsignedInteger('word_count');
            $table->unsignedSmallInteger('evidence_count');
            $table->decimal('confidence_score', 7, 4)->nullable();
            $table->json('warnings')->nullable();
            $table->timestamp('calculated_at');
            $table->timestamps();

            $table->unique(
                ['transcript_document_id', 'provider', 'algorithm_version'],
                'transcript_structure_document_provider_version_unique',
            );
            $table->index(['user_id', 'analyzer_run_id'], 'transcript_structure_owner_analyzer_index');
        });

        Schema::create('transcript_structure_insights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transcript_structure_profile_id')
                ->constrained('transcript_structure_profiles', 'id', 'ts_insights_profile_fk')
                ->cascadeOnDelete();
            $table->string('kind', 32);
            $table->string('label', 255);
            $table->text('detail')->nullable();
            $table->decimal('confidence', 7, 4);
            $table->unsignedSmallInteger('position');
            $table->unsignedInteger('start_offset');
            $table->unsignedInteger('end_offset');
            $table->unsignedInteger('start_ms')->nullable();
            $table->unsignedInteger('end_ms')->nullable();
            $table->timestamps();

            $table->index(
                ['transcript_structure_profile_id', 'kind', 'position'],
                'transcript_structure_insights_kind_position_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transcript_structure_insights');
        Schema::dropIfExists('transcript_structure_profiles');
    }
};
