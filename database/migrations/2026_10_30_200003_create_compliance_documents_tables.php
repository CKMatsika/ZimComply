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
        Schema::dropIfExists('compliance_documents');

        Schema::create('compliance_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->uuid('organisation_id')->index();
            $table->foreign('organisation_id')->references('id')->on('organisations')->cascadeOnDelete();
            $table->uuid('obligation_instance_id')->nullable();
            $table->foreign('obligation_instance_id')->references('id')->on('obligation_instances')->nullOnDelete();
            $table->string('document_type', 50);
            $table->string('title', 255);
            $table->string('file_path', 500);
            $table->string('file_name', 255);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('file_size_bytes');
            $table->string('sha256_hash', 64);
            $table->string('storage_disk', 32)->default('private');
            $table->string('verification_status', 32)->default('PENDING'); // PENDING, VERIFIED, REJECTED, EXPIRED
            $table->uuid('ai_verification_item_id')->nullable();
            $table->foreign('ai_verification_item_id')->references('id')->on('ai_verification_items')->nullOnDelete();
            $table->uuid('verified_by_user_id')->nullable();
            $table->foreign('verified_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            // Explicit short index names to adhere to MySQL/MariaDB 64-char identifier limit
            $table->index(['tenant_id', 'organisation_id', 'document_type'], 'comp_docs_tenant_org_type_idx');
            $table->index(['tenant_id', 'verification_status'], 'comp_docs_tenant_verif_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('compliance_documents');
    }
};
