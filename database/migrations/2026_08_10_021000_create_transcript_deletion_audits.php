<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transcript_deletion_audits', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('analyzer_run_public_id');
            $table->uuid('transcript_document_public_id');
            $table->string('provider_video_id', 32);
            $table->string('provider_version', 64);
            $table->string('input_format', 32);
            $table->string('language', 8);
            $table->unsignedInteger('character_count');
            $table->unsignedInteger('segment_count');
            $table->timestamp('deleted_at');
            $table->timestamps();

            $table->index(['user_id', 'deleted_at'], 'transcript_deletion_audits_owner_deleted_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transcript_deletion_audits');
    }
};
