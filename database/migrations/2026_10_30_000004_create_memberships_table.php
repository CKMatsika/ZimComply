<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memberships', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('organisation_id')->nullable()->constrained('organisations')->cascadeOnDelete();
            $table->string('role')->default('ADMIN'); // OWNER, ADMIN, ACCOUNTANT, HR_OFFICER, COMPLIANCE_OFFICER, TENDER_OFFICER, VIEWER
            $table->string('status')->default('ACTIVE'); // ACTIVE, INVITED, SUSPENDED
            $table->timestamps();

            $table->index(['tenant_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memberships');
    }
};
