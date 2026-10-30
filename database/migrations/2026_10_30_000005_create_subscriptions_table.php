<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('plan_code')->default('REGISTRATION_STARTER'); // REGISTRATION_STARTER, COMPLIANCE_CORE, FLEET_LICENSING, TENDER_INTELLIGENCE, ACCOUNTING_PRO
            $table->string('status')->default('TRIAL'); // TRIAL, ACTIVE, CANCELLED, PAST_DUE
            $table->jsonb('entitlements')->nullable();
            $table->timestamp('starts_at')->useCurrent();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
