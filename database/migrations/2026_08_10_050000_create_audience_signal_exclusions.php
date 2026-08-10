<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audience_signal_exclusions', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('normalized_word', 64);
            $table->string('display_word', 64);
            $table->boolean('is_active')->default(true);
            $table->timestamp('excluded_at');
            $table->timestamp('restored_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'normalized_word'], 'audience_exclusions_owner_word_unique');
            $table->index(['user_id', 'is_active', 'display_word'], 'audience_exclusions_owner_active_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audience_signal_exclusions');
    }
};
