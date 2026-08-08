<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('favorites', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('research_project_id')->nullable()->constrained('research_projects')->nullOnDelete();
            $table->string('target_type', 32);
            $table->unsignedBigInteger('target_id');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'target_type', 'target_id'], 'favorites_owner_target_unique');
            $table->index(['user_id', 'research_project_id', 'updated_at'], 'favorites_owner_project_updated_index');
            $table->index(['target_type', 'target_id'], 'favorites_target_index');
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('name_key', 80);
            $table->string('color', 16)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'name_key'], 'tags_owner_name_unique');
            $table->index(['user_id', 'updated_at']);
        });

        Schema::create('taggables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->string('target_type', 32);
            $table->unsignedBigInteger('target_id');
            $table->timestamps();

            $table->unique(['tag_id', 'target_type', 'target_id'], 'taggables_tag_target_unique');
            $table->index(['target_type', 'target_id'], 'taggables_target_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taggables');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('favorites');
    }
};
