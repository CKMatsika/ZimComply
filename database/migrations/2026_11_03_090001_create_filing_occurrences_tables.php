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
        Schema::create('filing_occurrences', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Tenant & Organisation Isolation
            $table->uuid('tenant_id')->index();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            $table->uuid('organisation_id')->index();
            $table->foreign('organisation_id')->references('id')->on('organisations')->cascadeOnDelete();

            // Parent Obligation & Definition References
            $table->uuid('obligation_instance_id')->index();
            $table->foreign('obligation_instance_id')->references('id')->on('obligation_instances')->cascadeOnDelete();

            $table->uuid('obligation_definition_id')->index();
            $table->foreign('obligation_definition_id')->references('id')->on('obligation_definitions')->cascadeOnDelete();

            // Governed Rule Version & Schedule Identity
            $table->uuid('rule_version_id')->nullable()->index();
            $table->foreign('rule_version_id')->references('id')->on('rule_versions')->nullOnDelete();

            $table->string('schedule_identity', 64)->default('RETURN'); // RETURN, PAYMENT, COMBINED, EVENT
            $table->string('filing_frequency', 32); // MONTHLY, QUARTERLY, ANNUAL, FIXED_DATE, EVENT_DRIVEN

            // Filing Period Boundaries
            $table->date('filing_period_start');
            $table->date('filing_period_end');
            $table->string('filing_period_label', 64)->nullable();

            // Statutory Due Dates & Calculation Basis
            $table->date('original_statutory_due_date')->nullable();
            $table->date('current_due_date')->nullable();
            $table->date('payment_due_date')->nullable();
            $table->json('deadline_calculation_basis')->nullable();
            $table->string('timezone', 64)->default('Africa/Harare');

            // Assignment
            $table->uuid('assigned_to_user_id')->nullable()->index();
            $table->foreign('assigned_to_user_id')->references('id')->on('users')->nullOnDelete();

            // Internal Filing Workflow Status (distinct from parent obligation lifecycle)
            $table->string('workflow_status', 32)->default('NOT_STARTED');
            // NOT_STARTED, PREPARING, READY_FOR_REVIEW, INTERNALLY_APPROVED, SUBMITTED, ACKNOWLEDGED, REJECTED, CLOSED

            // Official Regulatory Submission / Acceptance Status (separately tracked)
            $table->string('official_submission_status', 32)->default('NOT_SUBMITTED');
            // NOT_SUBMITTED, DECLARED_SUBMITTED, OFFICIALLY_ACKNOWLEDGED, OFFICIALLY_REJECTED

            // References & Timestamps
            $table->string('submission_reference', 128)->nullable();
            $table->string('official_receipt_number', 128)->nullable();

            $table->timestamp('internally_approved_at')->nullable();
            $table->uuid('internally_approved_by_user_id')->nullable();
            $table->foreign('internally_approved_by_user_id')->references('id')->on('users')->nullOnDelete();

            $table->timestamp('submitted_at')->nullable();
            $table->uuid('submitted_by_user_id')->nullable();
            $table->foreign('submitted_by_user_id')->references('id')->on('users')->nullOnDelete();

            $table->timestamp('acknowledged_at')->nullable();
            $table->uuid('acknowledged_by_user_id')->nullable();
            $table->foreign('acknowledged_by_user_id')->references('id')->on('users')->nullOnDelete();

            $table->timestamp('closed_at')->nullable();
            $table->uuid('closed_by_user_id')->nullable();
            $table->foreign('closed_by_user_id')->references('id')->on('users')->nullOnDelete();

            // Evidence & Source Governance
            $table->string('evidence_status', 32)->default('NONE');
            // NONE, DOCUMENT_UPLOADED, HUMAN_REVIEWED, INTERNALLY_SUBMITTED, OFFICIALLY_ACKNOWLEDGED, REJECTED_EVIDENCE, EXPIRED_EVIDENCE
            $table->string('source_citation', 255)->nullable();
            $table->string('verification_status', 32)->default('VERIFIED_OFFICIAL');
            // VERIFIED_OFFICIAL, DEADLINE_UNVERIFIED, UNVERIFIED_HOLD

            $table->text('internal_notes')->nullable();
            $table->boolean('is_obligation_currently_applicable')->default(true);
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();

            // Unique constraint preventing duplicate occurrences for the same organisation, obligation, filing period, and schedule identity
            $table->unique(
                ['organisation_id', 'obligation_instance_id', 'filing_period_start', 'filing_period_end', 'schedule_identity'],
                'filing_occ_org_inst_period_sched_uniq'
            );

            $table->index(['tenant_id', 'organisation_id', 'current_due_date'], 'filing_occ_ten_org_due_idx');
            $table->index(['tenant_id', 'organisation_id', 'workflow_status'], 'filing_occ_ten_org_status_idx');
            $table->index(['tenant_id', 'assigned_to_user_id'], 'filing_occ_ten_assigned_idx');
        });

        Schema::create('filing_occurrence_activity_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('tenant_id')->index();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            $table->uuid('organisation_id')->index();
            $table->foreign('organisation_id')->references('id')->on('organisations')->cascadeOnDelete();

            $table->uuid('filing_occurrence_id')->index();
            $table->foreign('filing_occurrence_id')->references('id')->on('filing_occurrences')->cascadeOnDelete();

            $table->uuid('user_id')->nullable()->index();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();

            $table->string('activity_type', 48);
            // GENERATED, STATUS_TRANSITION, ASSIGNMENT_CHANGED, EVIDENCE_LINKED, EVIDENCE_UNLINKED, DUE_DATE_AMENDED, RULE_AMENDED, NOTE_UPDATED
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32)->nullable();
            $table->string('from_official_status', 32)->nullable();
            $table->string('to_official_status', 32)->nullable();

            $table->uuid('from_user_id')->nullable();
            $table->foreign('from_user_id')->references('id')->on('users')->nullOnDelete();

            $table->uuid('to_user_id')->nullable();
            $table->foreign('to_user_id')->references('id')->on('users')->nullOnDelete();

            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'filing_occurrence_id', 'created_at'], 'fil_act_ten_occ_created_idx');
            $table->index(['organisation_id', 'created_at'], 'fil_act_org_created_idx');
        });

        Schema::table('compliance_documents', function (Blueprint $table) {
            if (!Schema::hasColumn('compliance_documents', 'filing_occurrence_id')) {
                $table->uuid('filing_occurrence_id')->nullable()->after('obligation_instance_id')->index();
                $table->foreign('filing_occurrence_id')->references('id')->on('filing_occurrences')->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('compliance_documents', function (Blueprint $table) {
            if (Schema::hasColumn('compliance_documents', 'filing_occurrence_id')) {
                $table->dropForeign(['filing_occurrence_id']);
                $table->dropColumn('filing_occurrence_id');
            }
        });

        Schema::dropIfExists('filing_occurrence_activity_logs');
        Schema::dropIfExists('filing_occurrences');
    }
};

