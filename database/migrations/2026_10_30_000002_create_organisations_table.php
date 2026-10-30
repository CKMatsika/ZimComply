<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organisations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('legal_name');
            $table->string('trading_name')->nullable();
            $table->string('entity_type')->default('PRIVATE_LIMITED_COMPANY'); // PRIVATE_LIMITED_COMPANY, PBC, PARTNERSHIP, SOLE_PROPRIETORSHIP, TRUST, FOREIGN_BRANCH
            $table->text('tin')->nullable(); // Encrypted Tax Identification Number
            $table->string('incorporation_number')->nullable();
            $table->date('incorporation_date')->nullable();
            $table->string('base_currency', 3)->default('USD');
            $table->smallInteger('fiscal_year_end_month')->default(12);
            $table->string('primary_email')->nullable();
            $table->string('primary_phone', 50)->nullable();
            $table->text('registered_office_address')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organisations');
    }
};
