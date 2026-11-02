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
            if (!Schema::hasColumn('compliance_documents', 'malware_scan_status')) {
                $table->string('malware_scan_status', 32)->default('SKIPPED')->after('storage_disk');
            }

            if (!Schema::hasColumn('compliance_documents', 'last_extraction_error')) {
                $table->text('last_extraction_error')->nullable()->after('extracted_metadata');
            }

            if (!Schema::hasColumn('compliance_documents', 'extraction_attempts')) {
                $table->unsignedInteger('extraction_attempts')->default(0)->after('last_extraction_error');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('compliance_documents', function (Blueprint $table) {
            $table->dropColumn([
                'malware_scan_status',
                'last_extraction_error',
                'extraction_attempts',
            ]);
        });
    }
};
