<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_usage_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('research_run_id')->nullable();
            $table->string('provider', 32);
            $table->string('quota_bucket', 64);
            $table->string('endpoint', 64);
            $table->unsignedSmallInteger('request_count')->default(1);
            $table->unsignedInteger('estimated_cost');
            $table->string('outcome', 32);
            $table->string('safe_error_code', 64)->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['provider', 'quota_bucket', 'occurred_at'], 'api_usage_provider_bucket_time_index');
            $table->index(['user_id', 'occurred_at']);
            $table->index(['research_run_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_usage_events');
    }
};
