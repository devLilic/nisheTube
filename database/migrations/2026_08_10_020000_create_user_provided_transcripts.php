<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transcript_documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('analyzer_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('video_id')->constrained()->restrictOnDelete();
            $table->string('provider', 64);
            $table->string('provider_version', 64);
            $table->string('status', 32);
            $table->string('input_format', 32);
            $table->string('language', 8)->default('und');
            $table->longText('source_text');
            $table->longText('plain_text');
            $table->unsignedInteger('character_count');
            $table->unsignedInteger('segment_count');
            $table->string('checksum_sha256', 64);
            $table->json('warnings')->nullable();
            $table->timestamp('rights_confirmed_at');
            $table->timestamp('provided_at');
            $table->timestamps();

            $table->index(['user_id', 'analyzer_run_id', 'created_at'], 'transcripts_owner_analyzer_created_index');
            $table->index(['user_id', 'provided_at'], 'transcripts_owner_provided_index');
            $table->unique(['analyzer_run_id', 'checksum_sha256'], 'transcripts_analyzer_checksum_unique');
        });

        Schema::create('transcript_segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transcript_document_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->unsignedBigInteger('start_ms')->nullable();
            $table->unsignedBigInteger('end_ms')->nullable();
            $table->text('text');
            $table->timestamps();

            $table->unique(['transcript_document_id', 'position'], 'transcript_segments_document_position_unique');
            $table->index(['transcript_document_id', 'start_ms'], 'transcript_segments_document_start_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transcript_segments');
        Schema::dropIfExists('transcript_documents');
    }
};
