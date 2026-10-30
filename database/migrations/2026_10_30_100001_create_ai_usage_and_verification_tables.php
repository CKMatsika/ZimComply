<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ai_usage_ledgers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->uuid('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->string('feature_code', 64)->index();
            $table->string('provider', 64);
            $table->string('model', 64);
            $table->unsignedInteger('prompt_tokens')->default(0);
            $table->unsignedInteger('completion_tokens')->default(0);
            $table->unsignedInteger('total_tokens')->default(0);
            $table->decimal('estimated_cost_usd', 10, 6)->default(0.000000);
            $table->string('status', 32)->default('SUCCESS');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'created_at']);
            $table->index(['tenant_id', 'feature_code']);
        });

        Schema::create('ai_verification_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->uuid('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->string('feature_code', 64)->index();
            $table->string('entity_type', 128);
            $table->string('entity_id', 64)->nullable();
            $table->json('proposed_payload');
            $table->json('verified_payload')->nullable();
            $table->decimal('confidence_score', 4, 3)->default(0.000);
            $table->string('status', 32)->default('PENDING'); // PENDING, VERIFIED, REJECTED, FLAGGED
            $table->uuid('verified_by_user_id')->nullable();
            $table->foreign('verified_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'feature_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_verification_items');
        Schema::dropIfExists('ai_usage_ledgers');
    }
};
