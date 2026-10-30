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
        Schema::create('regulatory_authorities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 50)->unique();
            $table->string('name', 255);
            $table->string('category', 50); // STATUTORY_REGULATOR, LOCAL_AUTHORITY, SECTOR_NEC, PROFESSIONAL_COUNCIL, PROCUREMENT_PLATFORM, INDUSTRY_ASSOCIATION
            $table->text('description')->nullable();
            $table->string('portal_url', 255)->nullable();
            $table->json('official_contact')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('category');
        });

        Schema::create('jurisdictions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 50)->unique();
            $table->string('name', 255);
            $table->string('province', 100);
            $table->string('council_type', 50); // CITY_COUNCIL, MUNICIPALITY, TOWN_COUNCIL, RURAL_DISTRICT_COUNCIL
            $table->timestamps();

            $table->index('province');
        });

        Schema::create('regulatory_sources', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('authority_id');
            $table->foreign('authority_id')->references('id')->on('regulatory_authorities')->cascadeOnDelete();
            $table->string('citation', 255);
            $table->string('title', 255);
            $table->string('act_chapter', 50)->nullable();
            $table->string('statutory_instrument_number', 100)->nullable();
            $table->date('gazette_date')->nullable();
            $table->string('official_url', 500)->nullable();
            $table->string('verification_status', 32)->default('VERIFIED_OFFICIAL'); // VERIFIED_OFFICIAL, REQUIRES_REVIEW, DRAFT
            $table->string('verified_by', 255)->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['authority_id', 'verification_status']);
        });

        Schema::create('rule_packs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('authority_id');
            $table->foreign('authority_id')->references('id')->on('regulatory_authorities')->cascadeOnDelete();
            $table->string('code', 100)->unique();
            $table->string('name', 255);
            $table->string('category', 50);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['authority_id', 'is_active']);
        });

        Schema::create('rule_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('rule_pack_id');
            $table->foreign('rule_pack_id')->references('id')->on('rule_packs')->cascadeOnDelete();
            $table->uuid('source_id');
            $table->foreign('source_id')->references('id')->on('regulatory_sources')->restrictOnDelete();
            $table->string('version_code', 50);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('status', 32)->default('DRAFT'); // DRAFT, PENDING_REVIEW, APPROVED, ACTIVE, SUPERSEDED, REJECTED
            $table->string('verification_status', 32)->default('REQUIRES_REVIEW'); // VERIFIED_OFFICIAL, REQUIRES_REVIEW, DRAFT
            $table->json('parameters');
            $table->text('change_summary')->nullable();
            $table->uuid('proposed_by_user_id')->nullable();
            $table->foreign('proposed_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->uuid('approved_by_user_id')->nullable();
            $table->foreign('approved_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['rule_pack_id', 'status', 'effective_from']);
            $table->unique(['rule_pack_id', 'version_code']);
        });

        Schema::create('obligation_definitions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('authority_id');
            $table->foreign('authority_id')->references('id')->on('regulatory_authorities')->cascadeOnDelete();
            $table->uuid('source_id')->nullable();
            $table->foreign('source_id')->references('id')->on('regulatory_sources')->nullOnDelete();
            $table->uuid('rule_pack_id')->nullable();
            $table->foreign('rule_pack_id')->references('id')->on('rule_packs')->nullOnDelete();
            $table->string('code', 100)->unique();
            $table->string('title', 255);
            $table->string('obligation_type', 50); // REGISTRATION, PERIODIC_RETURN, LICENCE_RENEWAL, LEVY_PAYMENT, TENDER_PREREQUISITE
            $table->string('legal_classification', 50); // MANDATORY_STATUTORY, CONDITIONAL_STATUTORY, CBA_COLLECTIVE_AGREEMENT, PROFESSIONAL_REGULATION, TENDER_SPECIFIC, OPTIONAL_ASSOCIATION
            $table->string('verification_status', 32)->default('VERIFIED_OFFICIAL'); // VERIFIED_OFFICIAL, REQUIRES_REVIEW, DRAFT
            $table->text('description')->nullable();
            $table->string('statutory_citation', 255)->nullable();
            $table->json('applicability_criteria');
            $table->json('prerequisites')->nullable();
            $table->json('required_documents')->nullable();
            $table->integer('estimated_processing_days')->default(14);
            $table->string('renewal_frequency', 32)->default('ONCE_OFF'); // ONCE_OFF, ANNUAL, MONTHLY, QUARTERLY
            $table->boolean('is_entity_level')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['authority_id', 'legal_classification']);
            $table->index(['legal_classification', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('obligation_definitions');
        Schema::dropIfExists('rule_versions');
        Schema::dropIfExists('rule_packs');
        Schema::dropIfExists('regulatory_sources');
        Schema::dropIfExists('jurisdictions');
        Schema::dropIfExists('regulatory_authorities');
    }
};
