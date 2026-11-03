<?php

namespace Database\Seeders;

use App\Domains\AuthAndTenant\Models\AuditEvent;
use App\Domains\AuthAndTenant\Models\Branch;
use App\Domains\AuthAndTenant\Models\Membership;
use App\Domains\AuthAndTenant\Models\Organisation;
use App\Domains\AuthAndTenant\Models\Subscription;
use App\Domains\AuthAndTenant\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 0. Seed Authoritative Zimbabwean Statutory Catalog
        $this->call(RegulatoryCatalogSeeder::class);

        // 1. Seed SME Owner & Enterprise Account
        $smeUser = User::create([
            'name' => 'Tatenda Moyo',
            'email' => 'admin@apex.co.zw',
            'phone' => '+263 77 234 5678',
            'password' => Hash::make('Password123!'),
            'email_verified_at' => now(),
        ]);

        $smeTenant = Tenant::create([
            'name' => 'Apex Engineering Group',
            'type' => 'SME',
            'status' => 'ACTIVE',
        ]);

        $apexOrg = Organisation::create([
            'tenant_id' => $smeTenant->id,
            'legal_name' => 'Apex Engineering & Fabrication (Pvt) Ltd',
            'trading_name' => 'Apex Steelworks',
            'entity_type' => 'PRIVATE_LIMITED_COMPANY',
            'tin' => '2001928475',
            'incorporation_number' => 'COBE-8921/2021',
            'incorporation_date' => '2021-04-12',
            'base_currency' => 'USD',
            'fiscal_year_end_month' => 12,
            'primary_email' => 'admin@apex.co.zw',
            'primary_phone' => '+263 77 234 5678',
            'registered_office_address' => 'Stand 412, Workington Industrial Area, Harare, Zimbabwe',
        ]);

        Branch::create([
            'tenant_id' => $smeTenant->id,
            'organisation_id' => $apexOrg->id,
            'name' => 'Harare Heavy Industrial Workshop',
            'code' => 'HRE-01',
            'city' => 'Harare',
            'physical_address' => 'Stand 412, Workington, Harare',
            'is_headquarters' => true,
        ]);

        Branch::create([
            'tenant_id' => $smeTenant->id,
            'organisation_id' => $apexOrg->id,
            'name' => 'Bulawayo Depot & Fabrication Yard',
            'code' => 'BYO-01',
            'city' => 'Bulawayo',
            'physical_address' => '14 Belmont South, Bulawayo',
            'is_headquarters' => false,
        ]);

        Membership::create([
            'tenant_id' => $smeTenant->id,
            'user_id' => $smeUser->id,
            'organisation_id' => $apexOrg->id,
            'role' => 'OWNER',
            'status' => 'ACTIVE',
        ]);

        Subscription::create([
            'tenant_id' => $smeTenant->id,
            'plan_code' => 'COMPLIANCE_CORE',
            'status' => 'ACTIVE',
            'entitlements' => [
                'max_companies' => 2,
                'registration_roadmap' => true,
                'compliance_wallet' => true,
                'praz_matching' => true,
                'payroll_enabled' => true,
            ],
            'starts_at' => now(),
            'expires_at' => now()->addYear(),
        ]);

        AuditEvent::create([
            'tenant_id' => $smeTenant->id,
            'user_id' => $smeUser->id,
            'event_type' => 'TENANT_SEEDED',
            'auditable_type' => Tenant::class,
            'auditable_id' => $smeTenant->id,
            'new_values' => ['name' => $smeTenant->name],
            'ip_address' => '127.0.0.1',
        ]);

        // 1b. Seed Secondary SME Member & Initial Assessment + Filing Occurrences for Apex Engineering
        $smeStaff = User::create([
            'name' => 'Farai Nyamukondiwa',
            'email' => 'farai@apex.co.zw',
            'phone' => '+263 77 345 6789',
            'password' => Hash::make('Password123!'),
            'email_verified_at' => now(),
        ]);

        Membership::create([
            'tenant_id' => $smeTenant->id,
            'user_id' => $smeStaff->id,
            'organisation_id' => $apexOrg->id,
            'role' => 'COMPLIANCE_OFFICER',
            'status' => 'ACTIVE',
        ]);

        $roadmapGen = app(\App\Domains\RegistrationEngine\Services\RegistrationRoadmapGenerator::class);
        $roadmapGen->generate(
            tenantId: $smeTenant->id,
            organisationId: $apexOrg->id,
            businessProfile: [
                'entity_type' => 'PRIVATE_LIMITED_COMPANY',
                'is_already_incorporated' => true,
                'industry_sector' => 'ENGINEERING',
                'employee_count' => 14,
                'pays_wages' => true,
                'annual_turnover_usd' => 95000.00,
                'bids_on_government_tenders' => true,
                'has_physical_premises' => true,
                'jurisdiction_codes' => ['ZW-HARARE'],
                'licensed_profession_involved' => true,
            ],
            assessmentDate: '2026-10-01',
            assessedByUserId: $smeUser->id
        );

        // Configure recurring compliance frequencies on applicable obligations for calendar demonstration
        $nssaOb = \App\Domains\RegistrationEngine\Models\ObligationInstance::withoutGlobalScopes()
            ->where('organisation_id', $apexOrg->id)
            ->whereHas('definition', fn ($q) => $q->where('code', 'NSSA_EMPLOYER_REG'))
            ->first();
        if ($nssaOb) {
            $nssaOb->update(['compliance_frequency' => 'MONTHLY', 'assigned_to_user_id' => $smeUser->id]);
        }

        $vatOb = \App\Domains\RegistrationEngine\Models\ObligationInstance::withoutGlobalScopes()
            ->where('organisation_id', $apexOrg->id)
            ->whereHas('definition', fn ($q) => $q->where('code', 'ZIMRA_VAT_REG'))
            ->first();
        if ($vatOb) {
            $vatOb->update(['compliance_frequency' => 'MONTHLY', 'assigned_to_user_id' => $smeStaff->id]);
        }

        $scheduleGen = app(\App\Domains\ComplianceEngine\Services\FilingScheduleGenerator::class);
        $scheduleGen->generateForOrganisation($apexOrg, '2026-10-01', 12, $smeUser->id);

        // 2. Seed Accounting Practice Multi-Client
        $consultantUser = User::create([
            'name' => 'Chipo Chiroro (CA Z)',
            'email' => 'consultant@chiroro.co.zw',
            'phone' => '+263 71 888 9999',
            'password' => Hash::make('Password123!'),
            'email_verified_at' => now(),
        ]);

        $firmTenant = Tenant::create([
            'name' => 'Chiroro & Partners Business Advisory',
            'type' => 'ACCOUNTING_FIRM',
            'status' => 'ACTIVE',
        ]);

        $clientOrg1 = Organisation::create([
            'tenant_id' => $firmTenant->id,
            'legal_name' => 'Mutare Agro-Processing Ltd',
            'trading_name' => 'Eastern Macadamias',
            'entity_type' => 'PRIVATE_LIMITED_COMPANY',
            'base_currency' => 'USD',
            'primary_email' => 'finance@mutareagro.co.zw',
        ]);

        $clientOrg2 = Organisation::create([
            'tenant_id' => $firmTenant->id,
            'legal_name' => 'Zambezi River Logistics (Pvt) Ltd',
            'trading_name' => 'Zambezi Haulage',
            'entity_type' => 'PRIVATE_LIMITED_COMPANY',
            'base_currency' => 'USD',
            'primary_email' => 'ops@zambezilogistics.co.zw',
        ]);

        Membership::create([
            'tenant_id' => $firmTenant->id,
            'user_id' => $consultantUser->id,
            'organisation_id' => $clientOrg1->id,
            'role' => 'ACCOUNTANT',
            'status' => 'ACTIVE',
        ]);

        Membership::create([
            'tenant_id' => $firmTenant->id,
            'user_id' => $consultantUser->id,
            'organisation_id' => $clientOrg2->id,
            'role' => 'ACCOUNTANT',
            'status' => 'ACTIVE',
        ]);

        Subscription::create([
            'tenant_id' => $firmTenant->id,
            'plan_code' => 'ACCOUNTING_PRO',
            'status' => 'ACTIVE',
            'entitlements' => [
                'max_companies' => 50,
                'multi_client_portfolio' => true,
                'bulk_payroll' => true,
                'praz_matching' => true,
            ],
            'starts_at' => now(),
            'expires_at' => now()->addYear(),
        ]);
    }
}
