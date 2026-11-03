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
        Schema::table('obligation_instances', function (Blueprint $table) {
            if (!Schema::hasColumn('obligation_instances', 'assigned_to_user_id')) {
                $table->uuid('assigned_to_user_id')->nullable()->after('exemption_reason');
            }
            $table->foreign('assigned_to_user_id')->references('id')->on('users')->nullOnDelete();

            $table->string('compliance_frequency', 32)->nullable()->after('assigned_to_user_id');
            $table->date('next_due_date')->nullable()->after('compliance_frequency');
            $table->timestamp('last_reviewed_at')->nullable()->after('next_due_date');
            $table->uuid('last_reviewed_by_user_id')->nullable()->after('last_reviewed_at');
            $table->foreign('last_reviewed_by_user_id')->references('id')->on('users')->nullOnDelete();

            $table->text('internal_notes')->nullable()->after('last_reviewed_by_user_id');
            $table->unsignedInteger('lock_version')->default(1)->after('internal_notes');

            $table->index(['tenant_id', 'assigned_to_user_id'], 'ob_inst_tenant_assigned_idx');
            $table->index(['tenant_id', 'next_due_date'], 'ob_inst_tenant_due_idx');
        });

        Schema::create('obligation_activity_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            $table->uuid('organisation_id')->index();
            $table->foreign('organisation_id')->references('id')->on('organisations')->cascadeOnDelete();

            $table->uuid('obligation_instance_id')->index();
            $table->foreign('obligation_instance_id')->references('id')->on('obligation_instances')->cascadeOnDelete();

            $table->uuid('user_id')->nullable()->index();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();

            $table->string('activity_type', 32); // STATUS_TRANSITION, ASSIGNMENT_CHANGED, NOTE_UPDATED, DUE_DATE_UPDATED, EVIDENCE_LINKED, EVIDENCE_UNLINKED
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32)->nullable();

            $table->uuid('from_user_id')->nullable();
            $table->foreign('from_user_id')->references('id')->on('users')->nullOnDelete();

            $table->uuid('to_user_id')->nullable();
            $table->foreign('to_user_id')->references('id')->on('users')->nullOnDelete();

            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'obligation_instance_id', 'created_at'], 'ob_act_ten_inst_created_idx');
            $table->index(['organisation_id', 'created_at'], 'ob_act_org_created_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('obligation_activity_logs');

        Schema::table('obligation_instances', function (Blueprint $table) {
            $table->dropForeign(['assigned_to_user_id']);
            $table->dropForeign(['last_reviewed_by_user_id']);
            $table->dropIndex('ob_inst_tenant_assigned_idx');
            $table->dropIndex('ob_inst_tenant_due_idx');

            $table->dropColumn([
                'assigned_to_user_id',
                'compliance_frequency',
                'next_due_date',
                'last_reviewed_at',
                'last_reviewed_by_user_id',
                'internal_notes',
                'lock_version',
            ]);
        });
    }
};
