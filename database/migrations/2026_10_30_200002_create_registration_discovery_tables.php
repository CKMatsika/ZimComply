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
        Schema::dropIfExists('obligation_instances');
        Schema::dropIfExists('assessment_results');
        Schema::dropIfExists('applicability_assessments');
        Schema::dropIfExists('registration_questionnaires');

        Schema::create('registration_questionnaires', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->uuid('organisation_id')->index();
            $table->foreign('organisation_id')->references('id')->on('organisations')->cascadeOnDelete();
            $table->string('status', 32)->default('DRAFT'); // DRAFT, COMPLETED, SUPERSEDED
            $table->integer('current_step')->default(1);
            $table->json('answers');
            $table->timestamp('completed_at')->nullable();
            $table->uuid('completed_by_user_id')->nullable();
            $table->foreign('completed_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->timestamps();

            // Explicit short index name to adhere to MySQL/MariaDB 64-char identifier limit
            $table->index(['tenant_id', 'organisation_id', 'status'], 'reg_quest_tenant_org_status_idx');
        });

        Schema::create('applicability_assessments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->uuid('organisation_id')->index();
            $table->foreign('organisation_id')->references('id')->on('organisations')->cascadeOnDelete();
            $table->uuid('questionnaire_id');
            $table->foreign('questionnaire_id')->references('id')->on('registration_questionnaires')->cascadeOnDelete();
            $table->string('assessment_type', 50)->default('INITIAL_REGISTRATION'); // INITIAL_REGISTRATION, REASSESSMENT, PERIODIC_AUDIT
            $table->json('evaluation_inputs');
            $table->uuid('assessed_by_user_id')->nullable();
            $table->foreign('assessed_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->timestamp('generated_at');
            $table->timestamps();

            // Explicit short index name to adhere to MySQL/MariaDB 64-char identifier limit
            $table->index(['tenant_id', 'organisation_id', 'generated_at'], 'app_assess_tenant_org_gen_idx');
        });

        Schema::create('assessment_results', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->uuid('assessment_id')->index();
            $table->foreign('assessment_id')->references('id')->on('applicability_assessments')->cascadeOnDelete();
            $table->uuid('obligation_definition_id');
            $table->foreign('obligation_definition_id')->references('id')->on('obligation_definitions')->cascadeOnDelete();
            $table->uuid('rule_version_id')->nullable();
            $table->foreign('rule_version_id')->references('id')->on('rule_versions')->restrictOnDelete();
            $table->string('decision', 32); // MANDATORY, CONDITIONAL, EXEMPT, NOT_APPLICABLE, UNVERIFIED_HOLD, REQUIRES_INFORMATION
            $table->string('legal_classification', 50); // MANDATORY_STATUTORY, CONDITIONAL_STATUTORY, CBA_COLLECTIVE_AGREEMENT, PROFESSIONAL_REGULATION, TENDER_SPECIFIC, OPTIONAL_ASSOCIATION
            $table->json('trigger_reasons'); // Explicit array of conditions that triggered this decision
            $table->string('rule_source_citation', 255)->nullable();
            $table->json('prerequisites_snapshot')->nullable();
            $table->json('required_documents_snapshot')->nullable();
            $table->timestamp('evaluated_at');
            $table->timestamps();

            // Explicit short index names to adhere to MySQL/MariaDB 64-char identifier limit
            $table->index(['tenant_id', 'assessment_id', 'decision'], 'assess_res_tenant_assess_dec_idx');
            $table->index(['tenant_id', 'obligation_definition_id'], 'assess_res_tenant_def_idx');
        });

        Schema::create('obligation_instances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->uuid('organisation_id')->index();
            $table->foreign('organisation_id')->references('id')->on('organisations')->cascadeOnDelete();
            $table->uuid('definition_id');
            $table->foreign('definition_id')->references('id')->on('obligation_definitions')->cascadeOnDelete();
            $table->uuid('assessment_result_id')->nullable();
            $table->foreign('assessment_result_id')->references('id')->on('assessment_results')->nullOnDelete();
            $table->string('status', 32)->default('NOT_STARTED'); // NOT_STARTED, IN_PROGRESS, AWAITING_VERIFICATION, COMPLIANT, EXEMPT, DISPUTED
            $table->string('evidence_status', 32)->default('UNKNOWN'); // VERIFIED_BY_ISSUER, DOCUMENT_SUPPORTED, USER_DECLARED, INFERRED, UNKNOWN
            $table->string('declared_registration_number', 100)->nullable();
            $table->date('declared_registered_at')->nullable();
            $table->text('exemption_reason')->nullable();
            $table->timestamps();

            // Explicit short index names to adhere to MySQL/MariaDB 64-char identifier limit
            $table->unique(['organisation_id', 'definition_id'], 'ob_inst_org_def_uniq');
            $table->index(['tenant_id', 'status'], 'ob_inst_tenant_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('obligation_instances');
        Schema::dropIfExists('assessment_results');
        Schema::dropIfExists('applicability_assessments');
        Schema::dropIfExists('registration_questionnaires');
    }
};
