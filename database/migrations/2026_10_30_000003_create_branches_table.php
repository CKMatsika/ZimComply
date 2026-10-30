<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 50)->nullable();
            $table->string('city', 100)->default('Harare');
            $table->text('physical_address');
            $table->boolean('is_headquarters')->default(false);
            $table->timestamps();

            $table->index(['tenant_id', 'organisation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
