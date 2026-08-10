<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_comment_ideas', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('public_comment_id')->nullable()->constrained('public_comments')->nullOnDelete();
            $table->foreignId('video_id')->constrained()->restrictOnDelete();
            $table->string('provider_comment_id');
            $table->text('comment_text');
            $table->timestamp('source_published_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['user_id', 'video_id', 'provider_comment_id'],
                'saved_comment_ideas_owner_video_provider_unique',
            );
            $table->index(['user_id', 'created_at'], 'saved_comment_ideas_owner_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_comment_ideas');
    }
};
