<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('semantic_topic_profiles', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('analyzer_run_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status', 24);
            $table->string('provenance', 24)->default('inferred');
            $table->string('provider', 64);
            $table->string('algorithm_version', 64);
            $table->string('language', 16);
            $table->string('niche_label')->nullable();
            $table->string('niche_key')->nullable();
            $table->decimal('niche_confidence', 8, 4)->nullable();
            $table->string('subniche_label')->nullable();
            $table->string('subniche_key')->nullable();
            $table->decimal('subniche_confidence', 8, 4)->nullable();
            $table->decimal('concentration_score', 8, 4)->nullable();
            $table->decimal('confidence_score', 8, 4)->nullable();
            $table->json('evidence_summary');
            $table->json('warnings')->nullable();
            $table->timestamp('calculated_at');
            $table->timestamps();

            $table->index(['user_id', 'calculated_at'], 'semantic_profiles_owner_calculated_index');
            $table->index(['user_id', 'language'], 'semantic_profiles_owner_language_index');
        });

        Schema::create('semantic_classifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('semantic_topic_profile_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 32);
            $table->string('label');
            $table->string('label_key');
            $table->decimal('confidence', 8, 4);
            $table->unsignedSmallInteger('position');
            $table->json('evidence_video_ids');
            $table->timestamps();

            $table->unique(['semantic_topic_profile_id', 'kind', 'label_key'], 'semantic_classifications_profile_kind_key_unique');
            $table->index(['kind', 'label_key'], 'semantic_classifications_kind_key_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('semantic_classifications');
        Schema::dropIfExists('semantic_topic_profiles');
    }
};
