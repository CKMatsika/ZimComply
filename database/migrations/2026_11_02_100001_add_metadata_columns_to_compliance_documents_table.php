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
        Schema::table('compliance_documents', function (Blueprint $table) {
            if (!Schema::hasColumn('compliance_documents', 'uploaded_by_user_id')) {
                $table->uuid('uploaded_by_user_id')->nullable()->after('organisation_id');
                $table->foreign('uploaded_by_user_id')->references('id')->on('users')->nullOnDelete();
            }

            if (!Schema::hasColumn('compliance_documents', 'processing_status')) {
                $table->string('processing_status', 32)->default('PENDING')->after('verification_status');
            }

            if (!Schema::hasColumn('compliance_documents', 'extracted_metadata')) {
                $table->json('extracted_metadata')->nullable()->after('rejection_reason');
            }

            if (!Schema::hasColumn('compliance_documents', 'expiry_date')) {
                $table->date('expiry_date')->nullable()->after('extracted_metadata');
            }

            if (!Schema::hasColumn('compliance_documents', 'issue_date')) {
                $table->date('issue_date')->nullable()->after('expiry_date');
            }

            if (!Schema::hasColumn('compliance_documents', 'issuing_authority')) {
                $table->string('issuing_authority', 128)->nullable()->after('issue_date');
            }

            $table->index(['tenant_id', 'processing_status'], 'comp_docs_tenant_proc_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('compliance_documents', function (Blueprint $table) {
            $table->dropIndex('comp_docs_tenant_proc_idx');
            $table->dropForeign(['uploaded_by_user_id']);
            $table->dropColumn([
                'uploaded_by_user_id',
                'processing_status',
                'extracted_metadata',
                'expiry_date',
                'issue_date',
                'issuing_authority',
            ]);
        });
    }
};
