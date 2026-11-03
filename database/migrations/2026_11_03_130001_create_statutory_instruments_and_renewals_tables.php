<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Statutory Instruments (Licences, Certificates, Permits, Registrations, Clearances)
        if (!Schema::hasTable('statutory_instruments')) {
            Schema::create('statutory_instruments', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('tenant_id')->index();
                $table->uuid('organisation_id')->index();
                $table->uuid('obligation_instance_id')->nullable()->index();
                $table->uuid('obligation_definition_id')->nullable()->index();
                $table->uuid('regulatory_authority_id')->nullable()->index();
                $table->uuid('rule_version_id')->nullable()->index();

                // LICENCE, CERTIFICATE, PERMIT, REGISTRATION, CLEARANCE
                $table->string('instrument_category', 32)->default('LICENCE');
                $table->string('title', 255);
                $table->string('issuing_authority_name', 255);
                $table->string('reference_number', 128)->nullable();
                $table->string('premises_or_scope', 255)->nullable();

                // Underlying instrument validity dates
                $table->date('issue_date')->nullable();
                $table->date('expiry_date')->nullable();

                // Source-governed renewal window & statutory deadline
                $table->date('renewal_window_opens_at')->nullable();
                $table->date('statutory_renewal_deadline')->nullable();
                // VERIFIED_OFFICIAL, DEADLINE_UNVERIFIED, UNVERIFIED_HOLD
                $table->string('deadline_verification_status', 32)->default('DEADLINE_UNVERIFIED');
                $table->json('deadline_calculation_basis')->nullable();
                $table->string('source_citation', 255)->nullable();

                // Separate legal validity status of the underlying instrument:
                // ACTIVE, EXPIRED, SUSPENDED, CANCELLED, SUPERSEDED, UNKNOWN
                $table->string('validity_status', 32)->default('UNKNOWN')->index();

                // Separate renewal application workflow status:
                // NONE, RENEWAL_UPCOMING, RENEWAL_IN_PROGRESS, AWAITING_DOCUMENTS, READY_FOR_SUBMISSION,
                // DECLARED_SUBMITTED, AWAITING_AUTHORITY_DECISION, RENEWED, REJECTED
                $table->string('current_workflow_status', 40)->default('NONE')->index();

                // Current active / verified document reference
                $table->uuid('current_document_id')->nullable()->index();
                $table->uuid('assigned_to_user_id')->nullable()->index();
                $table->uuid('created_by_user_id')->nullable();

                $table->text('suspension_or_cancellation_reason')->nullable();
                $table->text('notes')->nullable();

                // Versioned metadata and optimistic concurrency control
                $table->unsignedInteger('metadata_version')->default(1);
                $table->unsignedInteger('lock_version')->default(1);

                $table->timestamps();

                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('organisation_id')->references('id')->on('organisations')->cascadeOnDelete();
                $table->foreign('obligation_instance_id')->references('id')->on('obligation_instances')->nullOnDelete();
                $table->foreign('obligation_definition_id')->references('id')->on('obligation_definitions')->nullOnDelete();
                $table->foreign('regulatory_authority_id')->references('id')->on('regulatory_authorities')->nullOnDelete();
                $table->foreign('rule_version_id')->references('id')->on('rule_versions')->nullOnDelete();
                $table->foreign('current_document_id')->references('id')->on('compliance_documents')->nullOnDelete();
                $table->foreign('assigned_to_user_id')->references('id')->on('users')->nullOnDelete();
                $table->foreign('created_by_user_id')->references('id')->on('users')->nullOnDelete();

                $table->index(['tenant_id', 'organisation_id', 'validity_status'], 'stat_inst_tenant_org_val_idx');
                $table->index(['organisation_id', 'expiry_date'], 'stat_inst_org_expiry_idx');
            });
        }

        // 2. Renewal Attempts (Specific renewal applications for a StatutoryInstrument)
        if (!Schema::hasTable('renewal_attempts')) {
            Schema::create('renewal_attempts', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('tenant_id')->index();
                $table->uuid('organisation_id')->index();
                $table->uuid('statutory_instrument_id')->index();
                $table->uuid('obligation_instance_id')->nullable()->index();
                $table->uuid('rule_version_id')->nullable()->index();

                $table->unsignedInteger('attempt_number')->default(1);
                $table->boolean('is_active_attempt')->default(true)->index();
                // Set to 'ACTIVE' while is_active_attempt = true, and NULL once terminal (RENEWED, REJECTED, WITHDRAWN, ABANDONED, CANCELLED).
                // Enforces at DB level that at most one active renewal attempt can exist per instrument while allowing unlimited historical attempts.
                $table->string('active_attempt_guard', 16)->nullable();

                // RENEWAL_UPCOMING, RENEWAL_IN_PROGRESS, AWAITING_DOCUMENTS, READY_FOR_SUBMISSION,
                // DECLARED_SUBMITTED, AWAITING_AUTHORITY_DECISION, RENEWED, REJECTED, WITHDRAWN, ABANDONED, CANCELLED
                $table->string('workflow_status', 40)->default('RENEWAL_IN_PROGRESS')->index();

                // Prior instrument snapshot at the time renewal started
                $table->string('prior_reference_number', 128)->nullable();
                $table->date('prior_issue_date')->nullable();
                $table->date('prior_expiry_date')->nullable();
                $table->uuid('prior_document_id')->nullable();

                // Replacement instrument details upon authority approval & verification
                $table->string('replacement_reference_number', 128)->nullable();
                $table->date('replacement_issue_date')->nullable();
                $table->date('replacement_expiry_date')->nullable();
                $table->uuid('replacement_document_id')->nullable();

                // Source-governed deadline snapshot for this renewal cycle
                $table->date('statutory_renewal_deadline')->nullable();
                $table->string('deadline_verification_status', 32)->default('DEADLINE_UNVERIFIED');
                $table->json('deadline_calculation_basis')->nullable();

                // Ownership & lifecycle timestamps
                $table->uuid('assigned_to_user_id')->nullable()->index();
                $table->uuid('started_by_user_id')->nullable();
                $table->timestamp('started_at')->nullable();

                // Submission declaration (distinct from authority approval)
                $table->string('submission_reference', 128)->nullable();
                $table->uuid('submitted_by_user_id')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->string('authority_receipt_number', 128)->nullable();

                // Confirmed authority decision outcome: APPROVED_RENEWED, REJECTED, WITHDRAWN, ABANDONED
                $table->string('decision_outcome', 32)->nullable();
                $table->uuid('decision_recorded_by_user_id')->nullable();
                $table->timestamp('decision_recorded_at')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->text('notes')->nullable();

                $table->unsignedInteger('lock_version')->default(1);
                $table->timestamps();

                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('organisation_id')->references('id')->on('organisations')->cascadeOnDelete();
                $table->foreign('statutory_instrument_id')->references('id')->on('statutory_instruments')->cascadeOnDelete();
                $table->foreign('obligation_instance_id')->references('id')->on('obligation_instances')->nullOnDelete();
                $table->foreign('rule_version_id')->references('id')->on('rule_versions')->nullOnDelete();
                $table->foreign('prior_document_id')->references('id')->on('compliance_documents')->nullOnDelete();
                $table->foreign('replacement_document_id')->references('id')->on('compliance_documents')->nullOnDelete();
                $table->foreign('assigned_to_user_id')->references('id')->on('users')->nullOnDelete();
                $table->foreign('started_by_user_id')->references('id')->on('users')->nullOnDelete();
                $table->foreign('submitted_by_user_id')->references('id')->on('users')->nullOnDelete();
                $table->foreign('decision_recorded_by_user_id')->references('id')->on('users')->nullOnDelete();

                $table->unique(
                    ['statutory_instrument_id', 'active_attempt_guard'],
                    'renewal_attempts_single_active_uniq'
                );
                $table->unique(
                    ['statutory_instrument_id', 'attempt_number'],
                    'renewal_attempts_inst_number_uniq'
                );
            });
        }

        // 3. Dedicated Renewal Evidence Links (Preserving compliance_documents.filing_occurrence_id untouched)
        if (!Schema::hasTable('renewal_evidence_links')) {
            Schema::create('renewal_evidence_links', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('tenant_id')->index();
                $table->uuid('organisation_id')->index();
                $table->uuid('statutory_instrument_id')->index();
                $table->uuid('renewal_attempt_id')->nullable()->index();
                $table->uuid('compliance_document_id')->index();

                // CURRENT_INSTRUMENT, PRIOR_INSTRUMENT, REPLACEMENT_INSTRUMENT,
                // APPLICATION_SUPPORTING_EVIDENCE, AUTHORITY_RECEIPT,
                // REJECTION_CORRESPONDENCE, SUSPENSION_CANCELLATION_NOTICE
                $table->string('document_purpose', 48);

                $table->boolean('is_superseded')->default(false);
                $table->timestamp('superseded_at')->nullable();
                $table->uuid('superseded_by_document_id')->nullable();
                $table->uuid('linked_by_user_id')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('organisation_id')->references('id')->on('organisations')->cascadeOnDelete();
                $table->foreign('statutory_instrument_id')->references('id')->on('statutory_instruments')->cascadeOnDelete();
                $table->foreign('renewal_attempt_id')->references('id')->on('renewal_attempts')->nullOnDelete();
                $table->foreign('compliance_document_id')->references('id')->on('compliance_documents')->cascadeOnDelete();
                $table->foreign('superseded_by_document_id')->references('id')->on('compliance_documents')->nullOnDelete();
                $table->foreign('linked_by_user_id')->references('id')->on('users')->nullOnDelete();

                $table->index(
                    ['statutory_instrument_id', 'document_purpose'],
                    'ren_ev_link_inst_purpose_idx'
                );
            });
        }

        // 4. Immutable Renewal Activity & Audit Logs
        if (!Schema::hasTable('renewal_activity_logs')) {
            Schema::create('renewal_activity_logs', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('tenant_id')->index();
                $table->uuid('organisation_id')->index();
                $table->uuid('statutory_instrument_id')->index();
                $table->uuid('renewal_attempt_id')->nullable()->index();
                $table->uuid('user_id')->nullable()->index();

                $table->string('activity_type', 48);
                $table->string('from_validity_status', 32)->nullable();
                $table->string('to_validity_status', 32)->nullable();
                $table->string('from_workflow_status', 40)->nullable();
                $table->string('to_workflow_status', 40)->nullable();
                $table->text('notes')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('organisation_id')->references('id')->on('organisations')->cascadeOnDelete();
                $table->foreign('statutory_instrument_id')->references('id')->on('statutory_instruments')->cascadeOnDelete();
                $table->foreign('renewal_attempt_id')->references('id')->on('renewal_attempts')->nullOnDelete();
                $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('renewal_activity_logs');
        Schema::dropIfExists('renewal_evidence_links');
        Schema::dropIfExists('renewal_attempts');
        Schema::dropIfExists('statutory_instruments');
    }
};

