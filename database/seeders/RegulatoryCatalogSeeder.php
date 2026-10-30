<?php

namespace Database\Seeders;

use App\Domains\RegistrationEngine\Models\Jurisdiction;
use App\Domains\RegistrationEngine\Models\ObligationDefinition;
use App\Domains\RegistrationEngine\Models\RegulatoryAuthority;
use App\Domains\RegistrationEngine\Models\RegulatorySource;
use App\Domains\RegistrationEngine\Models\RulePack;
use App\Domains\RegistrationEngine\Models\RuleVersion;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class RegulatoryCatalogSeeder extends Seeder
{
    /**
     * Seed authoritative Zimbabwean regulatory authorities, legal sources,
     * rule packs, effective-dated versions, and obligation definitions.
     */
    public function run(): void
    {
        // ---------------------------------------------------------------------
        // 1. Jurisdictions (Municipalities & Local Authorities)
        // ---------------------------------------------------------------------
        $jurisdictions = [
            ['code' => 'ZW-HARARE', 'name' => 'City of Harare', 'province' => 'Harare Metropolitan', 'council_type' => 'CITY_COUNCIL'],
            ['code' => 'ZW-BULAWAYO', 'name' => 'City of Bulawayo', 'province' => 'Bulawayo Metropolitan', 'council_type' => 'CITY_COUNCIL'],
            ['code' => 'ZW-CHITUNGWIZA', 'name' => 'Chitungwiza Municipality', 'province' => 'Harare Metropolitan', 'council_type' => 'MUNICIPALITY'],
            ['code' => 'ZW-MUTARE', 'name' => 'City of Mutare', 'province' => 'Manicaland', 'council_type' => 'CITY_COUNCIL'],
            ['code' => 'ZW-GWERU', 'name' => 'City of Gweru', 'province' => 'Midlands', 'council_type' => 'CITY_COUNCIL'],
            ['code' => 'ZW-MASVINGO', 'name' => 'City of Masvingo', 'province' => 'Masvingo', 'council_type' => 'CITY_COUNCIL'],
        ];

        foreach ($jurisdictions as $j) {
            Jurisdiction::updateOrCreate(['code' => $j['code']], $j);
        }

        // ---------------------------------------------------------------------
        // 2. Regulatory Authorities
        // ---------------------------------------------------------------------
        $authorities = [
            [
                'code' => 'CIPZ',
                'name' => 'Companies and Intellectual Property Zimbabwe',
                'category' => 'STATUTORY_REGULATOR',
                'description' => 'Registrar of Companies responsible for incorporation, business entity registries, and annual returns under the COBE Act.',
                'portal_url' => 'https://cipz.co.zw',
                'official_contact' => ['phone' => '+263-242-777353', 'address' => 'Corner 4th Street and Central Avenue, Harare'],
            ],
            [
                'code' => 'ZIMRA',
                'name' => 'Zimbabwe Revenue Authority',
                'category' => 'STATUTORY_REGULATOR',
                'description' => 'National tax authority administering corporate income tax, VAT, PAYE, and tax clearance certificates via TaRMS.',
                'portal_url' => 'https://tarms.zimra.co.zw',
                'official_contact' => ['phone' => '+263-242-758891', 'address' => 'Kurima House, George Silundika Avenue, Harare'],
            ],
            [
                'code' => 'NSSA',
                'name' => 'National Social Security Authority',
                'category' => 'STATUTORY_REGULATOR',
                'description' => 'Statutory social security agency managing the National Pension Scheme (POBS) and Workers Compensation Insurance (APWCS).',
                'portal_url' => 'https://selfservice.nssa.org.zw',
                'official_contact' => ['phone' => '+263-242-706523', 'address' => 'NSSA House, Corner Sam Nujoma & Selous Avenue, Harare'],
            ],
            [
                'code' => 'ZIMDEF',
                'name' => 'Zimbabwe Manpower Development Fund',
                'category' => 'STATUTORY_REGULATOR',
                'description' => 'Statutory human capital development fund administering the mandatory 1% national training levy on gross wage bill.',
                'portal_url' => 'https://www.zimdef.org.zw',
                'official_contact' => ['phone' => '+263-242-780451', 'address' => 'ZIMDEF House, Rotten Row, Harare'],
            ],
            [
                'code' => 'PRAZ',
                'name' => 'Procurement Regulatory Authority of Zimbabwe',
                'category' => 'PROCUREMENT_PLATFORM',
                'description' => 'Statutory regulator for public procurement administering mandatory annual supplier registrations on the e-GP portal.',
                'portal_url' => 'https://egp.praz.org.zw',
                'official_contact' => ['phone' => '+263-242-764043', 'address' => '76 Samora Machel Avenue, Harare'],
            ],
            [
                'code' => 'COH',
                'name' => 'City of Harare Licensing Department',
                'category' => 'LOCAL_AUTHORITY',
                'description' => 'Municipal licensing authority responsible for premises inspections, health permits, and business operating licences in Harare.',
                'portal_url' => 'https://hararecity.co.zw',
                'official_contact' => ['phone' => '+263-242-753000', 'address' => 'Town House, Julius Nyerere Way, Harare'],
            ],
            [
                'code' => 'COB',
                'name' => 'City of Bulawayo Health & Licensing',
                'category' => 'LOCAL_AUTHORITY',
                'description' => 'Municipal licensing authority responsible for premises permits and commercial trading licences in Bulawayo.',
                'portal_url' => 'https://cityofbulawayo.co.zw',
                'official_contact' => ['phone' => '+263-292-75011', 'address' => 'Tower Block, Leopold Takawira Avenue, Bulawayo'],
            ],
            [
                'code' => 'NEC_ENG',
                'name' => 'NEC for the Engineering, Iron and Steel Industry',
                'category' => 'SECTOR_NEC',
                'description' => 'National Employment Council establishing collective bargaining agreements, minimum wage grades, and pension dues for engineering and steel.',
                'portal_url' => 'https://nec-engineering.org.zw',
                'official_contact' => ['phone' => '+263-242-747352', 'address' => 'Harare, Zimbabwe'],
            ],
            [
                'code' => 'NEC_COMM',
                'name' => 'NEC for Commercial Sectors of Zimbabwe',
                'category' => 'SECTOR_NEC',
                'description' => 'National Employment Council governing terms of employment and dues for commercial trade, retail, and office workers.',
                'portal_url' => 'https://neccommercial.org.zw',
                'official_contact' => ['phone' => '+263-242-772841', 'address' => 'Harare, Zimbabwe'],
            ],
            [
                'code' => 'ECZ',
                'name' => 'Engineering Council of Zimbabwe',
                'category' => 'PROFESSIONAL_COUNCIL',
                'description' => 'Statutory regulatory council for practicing professional engineers and engineering firms in Zimbabwe.',
                'portal_url' => 'https://ecz.co.zw',
                'official_contact' => ['phone' => '+263-242-746358', 'address' => 'Harare, Zimbabwe'],
            ],
            [
                'code' => 'CZI',
                'name' => 'Confederation of Zimbabwe Industries',
                'category' => 'INDUSTRY_ASSOCIATION',
                'description' => 'Voluntary national industrial association advocating for manufacturing and commercial enterprise interests.',
                'portal_url' => 'https://czi.co.zw',
                'official_contact' => ['phone' => '+263-242-251490', 'address' => '31 Josiah Chinamano Avenue, Harare'],
            ],
        ];

        $authorityModels = [];
        foreach ($authorities as $a) {
            $authorityModels[$a['code']] = RegulatoryAuthority::updateOrCreate(['code' => $a['code']], $a);
        }

        // ---------------------------------------------------------------------
        // 3. Regulatory Legal Sources (Gazetted Acts & Statutory Instruments)
        // ---------------------------------------------------------------------
        $sources = [
            'COBE_ACT' => [
                'authority_id' => $authorityModels['CIPZ']->id,
                'citation' => 'Companies and Other Business Entities Act [Chapter 24:31]',
                'title' => 'An Act to provide for the incorporation, registration, management, and dissolution of companies and other business entities.',
                'act_chapter' => '24:31',
                'statutory_instrument_number' => null,
                'gazette_date' => '2019-11-15',
                'official_url' => 'https://www.veritaszim.net/node/3815',
                'verification_status' => 'VERIFIED_OFFICIAL',
                'notes' => 'Primary statutory basis for Private Limited Companies and PBCs.',
            ],
            'INCOME_TAX_ACT' => [
                'authority_id' => $authorityModels['ZIMRA']->id,
                'citation' => 'Income Tax Act [Chapter 23:06]',
                'title' => 'An Act to provide for the taxation of incomes and matters incidental thereto.',
                'act_chapter' => '23:06',
                'statutory_instrument_number' => null,
                'gazette_date' => '1967-04-01',
                'official_url' => 'https://www.zimra.co.zw',
                'verification_status' => 'VERIFIED_OFFICIAL',
                'notes' => 'Mandatory TIN registration under section 37; tax clearance under section 80.',
            ],
            'VAT_ACT' => [
                'authority_id' => $authorityModels['ZIMRA']->id,
                'citation' => 'Value Added Tax Act [Chapter 23:12]',
                'title' => 'An Act to provide for taxation in respect of the supply of goods and services.',
                'act_chapter' => '23:12',
                'statutory_instrument_number' => null,
                'gazette_date' => '2004-01-01',
                'official_url' => 'https://www.zimra.co.zw',
                'verification_status' => 'VERIFIED_OFFICIAL',
                'notes' => 'Compulsory VAT registration threshold under section 23.',
            ],
            'NSSA_ACT' => [
                'authority_id' => $authorityModels['NSSA']->id,
                'citation' => 'National Social Security Authority Act [Chapter 17:04]',
                'title' => 'An Act to provide for the establishment of social security schemes and the constitution of the Authority.',
                'act_chapter' => '17:04',
                'statutory_instrument_number' => 'SI 393 of 1993 & SI 68 of 1990',
                'gazette_date' => '1990-03-01',
                'official_url' => 'https://www.nssa.org.zw',
                'verification_status' => 'VERIFIED_OFFICIAL',
                'notes' => 'POBS mandatory pension and APWCS workers compensation schemes.',
            ],
            'ZIMDEF_ACT' => [
                'authority_id' => $authorityModels['ZIMDEF']->id,
                'citation' => 'Manpower Planning and Development Act [Chapter 28:02]',
                'title' => 'An Act to establish the Zimbabwe Manpower Development Fund and provide for training levies.',
                'act_chapter' => '28:02',
                'statutory_instrument_number' => null,
                'gazette_date' => '1994-11-01',
                'official_url' => 'https://www.zimdef.org.zw',
                'verification_status' => 'VERIFIED_OFFICIAL',
                'notes' => '1% gross monthly wage bill training levy under Section 53.',
            ],
            'PPDPA_ACT' => [
                'authority_id' => $authorityModels['PRAZ']->id,
                'citation' => 'Public Procurement and Disposal of Public Assets Act [Chapter 22:23]',
                'title' => 'An Act to regulate the public procurement of goods, works and services by statutory bodies.',
                'act_chapter' => '22:23',
                'statutory_instrument_number' => 'SI 5 of 2018',
                'gazette_date' => '2018-01-01',
                'official_url' => 'https://www.praz.org.zw',
                'verification_status' => 'VERIFIED_OFFICIAL',
                'notes' => 'Mandatory supplier e-GP registration for government tender eligibility.',
            ],
            'URBAN_COUNCILS_ACT' => [
                'authority_id' => $authorityModels['COH']->id,
                'citation' => 'Urban Councils Act [Chapter 29:15]',
                'title' => 'An Act to provide for the establishment of municipalities and towns and licensing of premises.',
                'act_chapter' => '29:15',
                'statutory_instrument_number' => null,
                'gazette_date' => '1996-03-15',
                'official_url' => 'https://www.hararecity.co.zw',
                'verification_status' => 'VERIFIED_OFFICIAL',
                'notes' => 'Empowers municipal local authorities to inspect and licence operating premises.',
            ],
            'LABOUR_ACT_ENG' => [
                'authority_id' => $authorityModels['NEC_ENG']->id,
                'citation' => 'Labour Act [Chapter 28:01] & SI 152 of 2017',
                'title' => 'Collective Bargaining Agreement: Engineering, Iron and Steel Industry.',
                'act_chapter' => '28:01',
                'statutory_instrument_number' => 'SI 152 of 2017',
                'gazette_date' => '2017-12-01',
                'official_url' => 'https://www.veritaszim.net',
                'verification_status' => 'VERIFIED_OFFICIAL',
                'notes' => 'Mandatory collective bargaining coverage for engineering sector employers.',
            ],
            'LABOUR_ACT_COMM' => [
                'authority_id' => $authorityModels['NEC_COMM']->id,
                'citation' => 'Labour Act [Chapter 28:01] & SI 135 of 2011',
                'title' => 'Collective Bargaining Agreement: Commercial Sectors of Zimbabwe.',
                'act_chapter' => '28:01',
                'statutory_instrument_number' => 'SI 135 of 2011',
                'gazette_date' => '2011-10-14',
                'official_url' => 'https://www.veritaszim.net',
                'verification_status' => 'VERIFIED_OFFICIAL',
                'notes' => 'Mandatory collective bargaining coverage for commercial sector employers.',
            ],
            'ECZ_ACT' => [
                'authority_id' => $authorityModels['ECZ']->id,
                'citation' => 'Engineering Council Act [Chapter 27:22]',
                'title' => 'An Act to establish the Engineering Council of Zimbabwe and regulate practicing engineers and firms.',
                'act_chapter' => '27:22',
                'statutory_instrument_number' => null,
                'gazette_date' => '2008-01-01',
                'official_url' => 'https://www.ecz.co.zw',
                'verification_status' => 'VERIFIED_OFFICIAL',
                'notes' => 'Statutory professional regulation for engineering companies and consultancies.',
            ],
            'CZI_CONSTITUTION' => [
                'authority_id' => $authorityModels['CZI']->id,
                'citation' => 'CZI Membership Charter & Constitution',
                'title' => 'Voluntary Industry Association Rules',
                'act_chapter' => null,
                'statutory_instrument_number' => null,
                'gazette_date' => null,
                'official_url' => 'https://czi.co.zw',
                'verification_status' => 'REQUIRES_REVIEW',
                'notes' => 'Voluntary association membership. Excluded from mandatory statutory compliance roadmaps.',
            ],
        ];

        $sourceModels = [];
        foreach ($sources as $key => $s) {
            $sourceModels[$key] = RegulatorySource::updateOrCreate(
                ['citation' => $s['citation']],
                $s
            );
        }

        // ---------------------------------------------------------------------
        // 4. Rule Packs & Published Rule Versions
        // ---------------------------------------------------------------------
        $rulePacks = [
            'CIPZ_FORMATION' => [
                'authority_id' => $authorityModels['CIPZ']->id,
                'code' => 'CIPZ_FORMATION_RULES',
                'name' => 'CIPZ Incorporation & Business Formation Rules',
                'category' => 'ENTITY_REGISTRATION',
                'description' => 'Requirements for reserving names and incorporating Private Limited Companies and PBCs.',
                'source_key' => 'COBE_ACT',
                'version' => '2024.1',
                'parameters' => [
                    'supported_entities' => ['PRIVATE_LIMITED_COMPANY', 'PBC'],
                    'mandatory_filings' => ['CR5', 'CR6', 'CR14', 'MEMORANDUM', 'ARTICLES'],
                    'name_search_validity_days' => 30,
                ],
            ],
            'ZIMRA_CORE_TAX' => [
                'authority_id' => $authorityModels['ZIMRA']->id,
                'code' => 'ZIMRA_CORE_TAX_RULES',
                'name' => 'ZIMRA Income Tax & Registration Rules',
                'category' => 'TAXATION',
                'description' => 'Mandatory TIN registration, QPD schedule, and ITF263 tax clearance rules.',
                'source_key' => 'INCOME_TAX_ACT',
                'version' => '2026.1',
                'parameters' => [
                    'mandatory_within_days' => 30,
                    'withholding_tax_rate_without_itf263' => 0.30,
                    'requires_clean_tax_record' => true,
                ],
            ],
            'ZIMRA_VAT' => [
                'authority_id' => $authorityModels['ZIMRA']->id,
                'code' => 'ZIMRA_VAT_RULES',
                'name' => 'ZIMRA Value Added Tax Registration Rules',
                'category' => 'TAXATION',
                'description' => 'Compulsory VAT registration threshold rules under Section 23 of the VAT Act.',
                'source_key' => 'VAT_ACT',
                'version' => '2026.1',
                'parameters' => [
                    'compulsory_turnover_threshold_usd' => 25000.00,
                    'turnover_evaluation_period_months' => 12,
                    'voluntary_registration_allowed' => true,
                    'return_due_day' => 25,
                ],
            ],
            'NSSA_EMPLOYER' => [
                'authority_id' => $authorityModels['NSSA']->id,
                'code' => 'NSSA_EMPLOYER_RULES',
                'name' => 'NSSA Employer Social Security Rules',
                'category' => 'SOCIAL_SECURITY',
                'description' => 'Mandatory registration rules for employers with 1 or more employees.',
                'source_key' => 'NSSA_ACT',
                'version' => '2026.1',
                'parameters' => [
                    'min_employees_for_compulsory_reg' => 1,
                    'registration_deadline_days' => 30,
                    'schemes' => ['POBS', 'APWCS'],
                    'due_day_of_month' => 10,
                ],
            ],
            'ZIMDEF_LEVY' => [
                'authority_id' => $authorityModels['ZIMDEF']->id,
                'code' => 'ZIMDEF_LEVY_RULES',
                'name' => 'ZIMDEF Manpower Training Levy Rules',
                'category' => 'LABOUR',
                'description' => '1% gross monthly wage bill training levy rules.',
                'source_key' => 'ZIMDEF_ACT',
                'version' => '2026.1',
                'parameters' => [
                    'levy_rate' => 0.01,
                    'min_employees' => 1,
                    'statutory_exemptions' => ['DIPLOMATIC', 'RELIGIOUS_CHARITABLE', 'EDUCATIONAL'],
                ],
            ],
            'PRAZ_PROCUREMENT' => [
                'authority_id' => $authorityModels['PRAZ']->id,
                'code' => 'PRAZ_PROCUREMENT_RULES',
                'name' => 'PRAZ Public Procurement Supplier Registration Rules',
                'category' => 'PROCUREMENT',
                'description' => 'Mandatory annual e-GP registration rules for public procurement eligibility.',
                'source_key' => 'PPDPA_ACT',
                'version' => '2026.1',
                'parameters' => [
                    'mandatory_for_public_tenders' => true,
                    'requires_valid_itf263' => true,
                    'requires_nssa_clearance' => true,
                    'validity_calendar_year' => true,
                ],
            ],
        ];

        $rulePackModels = [];
        foreach ($rulePacks as $key => $rp) {
            $pack = RulePack::updateOrCreate(
                ['code' => $rp['code']],
                [
                    'authority_id' => $rp['authority_id'],
                    'code' => $rp['code'],
                    'name' => $rp['name'],
                    'category' => $rp['category'],
                    'description' => $rp['description'],
                    'is_active' => true,
                ]
            );

            $rulePackModels[$key] = $pack;

            // Seed active, verified RuleVersion
            RuleVersion::updateOrCreate(
                [
                    'rule_pack_id' => $pack->id,
                    'version_code' => $rp['version'],
                ],
                [
                    'source_id' => $sourceModels[$rp['source_key']]->id,
                    'version_code' => $rp['version'],
                    'effective_from' => '2024-01-01',
                    'effective_to' => null,
                    'status' => 'ACTIVE',
                    'verification_status' => 'VERIFIED_OFFICIAL',
                    'parameters' => $rp['parameters'],
                    'change_summary' => 'Authoritative baseline verified against gazetted Acts and statutory instruments.',
                    'approved_at' => Carbon::now(),
                ]
            );
        }

        // ---------------------------------------------------------------------
        // 5. Obligation Definitions (Classified & Tagged with Authoritative Criteria)
        // ---------------------------------------------------------------------
        $obligations = [
            [
                'code' => 'CIPZ_INCORP',
                'authority_id' => $authorityModels['CIPZ']->id,
                'source_id' => $sourceModels['COBE_ACT']->id,
                'rule_pack_id' => $rulePackModels['CIPZ_FORMATION']->id,
                'title' => 'CIPZ Certificate of Incorporation & Registered Office',
                'obligation_type' => 'REGISTRATION',
                'legal_classification' => 'MANDATORY_STATUTORY',
                'verification_status' => 'VERIFIED_OFFICIAL',
                'description' => 'Mandatory foundational registration to establish distinct legal personality under the Companies and Other Business Entities Act [Chapter 24:31].',
                'statutory_citation' => 'COBE Act [Chapter 24:31], s. 16',
                'applicability_criteria' => [
                    'entity_types' => ['PRIVATE_LIMITED_COMPANY', 'PBC'],
                    'is_new_business' => true,
                ],
                'prerequisites' => [],
                'required_documents' => ['MEMORANDUM_ARTICLES', 'CR6_REGISTERED_OFFICE', 'CR14_DIRECTORS'],
                'estimated_processing_days' => 7,
                'renewal_frequency' => 'ONCE_OFF',
                'is_entity_level' => true,
            ],
            [
                'code' => 'ZIMRA_TIN_REG',
                'authority_id' => $authorityModels['ZIMRA']->id,
                'source_id' => $sourceModels['INCOME_TAX_ACT']->id,
                'rule_pack_id' => $rulePackModels['ZIMRA_CORE_TAX']->id,
                'title' => 'ZIMRA Tax Identification Number (TIN) & TaRMS Registration',
                'obligation_type' => 'REGISTRATION',
                'legal_classification' => 'MANDATORY_STATUTORY',
                'verification_status' => 'VERIFIED_OFFICIAL',
                'description' => 'Compulsory registration of corporate taxpayer with the Zimbabwe Revenue Authority within 30 days of incorporation or trade commencement.',
                'statutory_citation' => 'Income Tax Act [Chapter 23:06], s. 37',
                'applicability_criteria' => [
                    'entity_types' => ['PRIVATE_LIMITED_COMPANY', 'PBC', 'PARTNERSHIP', 'SOLE_PROPRIETORSHIP', 'TRUST'],
                ],
                'prerequisites' => ['CIPZ_INCORP'],
                'required_documents' => ['CERTIFICATE_OF_INCORPORATION', 'CR6_REGISTERED_OFFICE', 'CR14_DIRECTORS', 'DIRECTOR_ID_COPIES'],
                'estimated_processing_days' => 5,
                'renewal_frequency' => 'ONCE_OFF',
                'is_entity_level' => true,
            ],
            [
                'code' => 'ZIMRA_VAT_REG',
                'authority_id' => $authorityModels['ZIMRA']->id,
                'source_id' => $sourceModels['VAT_ACT']->id,
                'rule_pack_id' => $rulePackModels['ZIMRA_VAT']->id,
                'title' => 'ZIMRA Value Added Tax (VAT) Registration',
                'obligation_type' => 'REGISTRATION',
                'legal_classification' => 'CONDITIONAL_STATUTORY',
                'verification_status' => 'VERIFIED_OFFICIAL',
                'description' => 'Compulsory for businesses with annual taxable turnover exceeding the statutory threshold of US$25,000 (or local currency equivalent); voluntary registration permitted under criteria.',
                'statutory_citation' => 'Value Added Tax Act [Chapter 23:12], s. 23',
                'applicability_criteria' => [
                    'min_annual_turnover_usd' => 25000.00,
                    'or_voluntary_declared' => true,
                ],
                'prerequisites' => ['ZIMRA_TIN_REG'],
                'required_documents' => ['CERTIFICATE_OF_INCORPORATION', 'ITF263_TAX_CLEARANCE', 'BANK_STATEMENTS_OR_PROJECTIONS'],
                'estimated_processing_days' => 10,
                'renewal_frequency' => 'ONCE_OFF',
                'is_entity_level' => true,
            ],
            [
                'code' => 'NSSA_EMPLOYER_REG',
                'authority_id' => $authorityModels['NSSA']->id,
                'source_id' => $sourceModels['NSSA_ACT']->id,
                'rule_pack_id' => $rulePackModels['NSSA_EMPLOYER']->id,
                'title' => 'NSSA Employer Registration (POBS Pension & APWCS Workers Comp)',
                'obligation_type' => 'REGISTRATION',
                'legal_classification' => 'MANDATORY_STATUTORY',
                'verification_status' => 'VERIFIED_OFFICIAL',
                'description' => 'Compulsory registration for any employer engaging 1 or more employees within 30 days of recruitment under the NSSA Act [Chapter 17:04].',
                'statutory_citation' => 'NSSA Act [Chapter 17:04], SI 393/1993 & SI 68/1990',
                'applicability_criteria' => [
                    'min_employees' => 1,
                    'pays_wages' => true,
                ],
                'prerequisites' => ['CIPZ_INCORP'],
                'required_documents' => ['CERTIFICATE_OF_INCORPORATION', 'CR6_REGISTERED_OFFICE', 'EMPLOYEE_REGISTER'],
                'estimated_processing_days' => 7,
                'renewal_frequency' => 'ONCE_OFF',
                'is_entity_level' => true,
            ],
            [
                'code' => 'ZIMDEF_LEVY_REG',
                'authority_id' => $authorityModels['ZIMDEF']->id,
                'source_id' => $sourceModels['ZIMDEF_ACT']->id,
                'rule_pack_id' => $rulePackModels['ZIMDEF_LEVY']->id,
                'title' => 'ZIMDEF 1% Manpower Training Levy Registration',
                'obligation_type' => 'REGISTRATION',
                'legal_classification' => 'MANDATORY_STATUTORY',
                'verification_status' => 'VERIFIED_OFFICIAL',
                'description' => 'Mandatory 1% monthly payroll levy on the gross wage bill of all non-exempt commercial employers under the Manpower Planning and Development Act.',
                'statutory_citation' => 'Manpower Planning and Development Act [Chapter 28:02], s. 53',
                'applicability_criteria' => [
                    'min_employees' => 1,
                    'is_exempt_diplomatic_or_religious' => false,
                ],
                'prerequisites' => ['CIPZ_INCORP', 'NSSA_EMPLOYER_REG'],
                'required_documents' => ['CERTIFICATE_OF_INCORPORATION', 'PAYROLL_SUMMARY'],
                'estimated_processing_days' => 5,
                'renewal_frequency' => 'ONCE_OFF',
                'is_entity_level' => true,
            ],
            [
                'code' => 'COH_BUSINESS_LICENCE',
                'authority_id' => $authorityModels['COH']->id,
                'source_id' => $sourceModels['URBAN_COUNCILS_ACT']->id,
                'rule_pack_id' => null,
                'title' => 'City of Harare Municipal Business Operating Licence & Health Permit',
                'obligation_type' => 'REGISTRATION',
                'legal_classification' => 'CONDITIONAL_STATUTORY',
                'verification_status' => 'VERIFIED_OFFICIAL',
                'description' => 'Mandatory annual local authority permit for occupying commercial, industrial, or retail premises within the City of Harare jurisdiction.',
                'statutory_citation' => 'Urban Councils Act [Chapter 29:15], s. 218',
                'applicability_criteria' => [
                    'has_physical_premises' => true,
                    'jurisdiction_codes' => ['ZW-HARARE'],
                ],
                'prerequisites' => ['CIPZ_INCORP'],
                'required_documents' => ['CERTIFICATE_OF_INCORPORATION', 'LEASE_AGREEMENT_OR_TITLE', 'HEALTH_INSPECTION_RECEIPT'],
                'estimated_processing_days' => 14,
                'renewal_frequency' => 'ANNUAL',
                'is_entity_level' => false,
            ],
            [
                'code' => 'NEC_ENG_REG',
                'authority_id' => $authorityModels['NEC_ENG']->id,
                'source_id' => $sourceModels['LABOUR_ACT_ENG']->id,
                'rule_pack_id' => null,
                'title' => 'NEC Engineering, Iron & Steel Industry Registration',
                'obligation_type' => 'REGISTRATION',
                'legal_classification' => 'CBA_COLLECTIVE_AGREEMENT',
                'verification_status' => 'VERIFIED_OFFICIAL',
                'description' => 'Mandatory collective bargaining registration for employers engaged in mechanical, fabrication, mining engineering, and structural steel manufacturing.',
                'statutory_citation' => 'Labour Act [Chapter 28:01], s. 63 & SI 152/2017',
                'applicability_criteria' => [
                    'industry_sectors' => ['ENGINEERING', 'MANUFACTURING_STEEL', 'MINING_FABRICATION'],
                    'min_employees' => 1,
                ],
                'prerequisites' => ['CIPZ_INCORP', 'NSSA_EMPLOYER_REG'],
                'required_documents' => ['CERTIFICATE_OF_INCORPORATION', 'CR14_DIRECTORS', 'EMPLOYEE_WAGE_SCHEDULE'],
                'estimated_processing_days' => 7,
                'renewal_frequency' => 'ONCE_OFF',
                'is_entity_level' => true,
            ],
            [
                'code' => 'NEC_COMM_REG',
                'authority_id' => $authorityModels['NEC_COMM']->id,
                'source_id' => $sourceModels['LABOUR_ACT_COMM']->id,
                'rule_pack_id' => null,
                'title' => 'NEC Commercial Sectors of Zimbabwe Registration',
                'obligation_type' => 'REGISTRATION',
                'legal_classification' => 'CBA_COLLECTIVE_AGREEMENT',
                'verification_status' => 'VERIFIED_OFFICIAL',
                'description' => 'Mandatory collective bargaining registration for commercial distribution, retail, wholesale, and clerical office services.',
                'statutory_citation' => 'Labour Act [Chapter 28:01], s. 63 & SI 135/2011',
                'applicability_criteria' => [
                    'industry_sectors' => ['COMMERCIAL_TRADE', 'RETAIL', 'WHOLESALE', 'IT_SERVICES'],
                    'min_employees' => 1,
                ],
                'prerequisites' => ['CIPZ_INCORP', 'NSSA_EMPLOYER_REG'],
                'required_documents' => ['CERTIFICATE_OF_INCORPORATION', 'EMPLOYEE_SCHEDULE'],
                'estimated_processing_days' => 7,
                'renewal_frequency' => 'ONCE_OFF',
                'is_entity_level' => true,
            ],
            [
                'code' => 'ITF263_CLEARANCE',
                'authority_id' => $authorityModels['ZIMRA']->id,
                'source_id' => $sourceModels['INCOME_TAX_ACT']->id,
                'rule_pack_id' => $rulePackModels['ZIMRA_CORE_TAX']->id,
                'title' => 'ZIMRA ITF263 Tax Clearance Certificate (Withholding Tax Exemption)',
                'obligation_type' => 'PERIODIC_RETURN',
                'legal_classification' => 'CONDITIONAL_STATUTORY',
                'verification_status' => 'VERIFIED_OFFICIAL',
                'description' => 'Exempts company from 30% withholding tax on commercial contracts; required prerequisite for public procurement tender participation.',
                'statutory_citation' => 'Income Tax Act [Chapter 23:06], s. 80',
                'applicability_criteria' => [
                    'requires_clean_tax_record' => true,
                    'or_bidding_tenders' => true,
                ],
                'prerequisites' => ['ZIMRA_TIN_REG'],
                'required_documents' => ['FILED_INCOME_TAX_RETURN', 'PAYE_RETURNS_UP_TO_DATE'],
                'estimated_processing_days' => 3,
                'renewal_frequency' => 'ANNUAL',
                'is_entity_level' => true,
            ],
            [
                'code' => 'PRAZ_SUPPLIER_REG',
                'authority_id' => $authorityModels['PRAZ']->id,
                'source_id' => $sourceModels['PPDPA_ACT']->id,
                'rule_pack_id' => $rulePackModels['PRAZ_PROCUREMENT']->id,
                'title' => 'PRAZ e-GP Public Procurement Supplier Registration',
                'obligation_type' => 'TENDER_PREREQUISITE',
                'legal_classification' => 'TENDER_SPECIFIC',
                'verification_status' => 'VERIFIED_OFFICIAL',
                'description' => 'Mandatory annual registration for all vendors and contractors intending to participate in Zimbabwean government and state-owned entity tenders.',
                'statutory_citation' => 'Public Procurement and Disposal of Public Assets Act [Chapter 22:23], s. 4',
                'applicability_criteria' => [
                    'intends_to_bid_government_tenders' => true,
                ],
                'prerequisites' => ['CIPZ_INCORP', 'ZIMRA_TIN_REG', 'ITF263_CLEARANCE', 'NSSA_EMPLOYER_REG'],
                'required_documents' => ['CERTIFICATE_OF_INCORPORATION', 'CR14_DIRECTORS', 'ITF263_TAX_CLEARANCE', 'NSSA_CLEARANCE'],
                'estimated_processing_days' => 5,
                'renewal_frequency' => 'ANNUAL',
                'is_entity_level' => true,
            ],
            [
                'code' => 'ECZ_FIRM_REG',
                'authority_id' => $authorityModels['ECZ']->id,
                'source_id' => $sourceModels['ECZ_ACT']->id,
                'rule_pack_id' => null,
                'title' => 'Engineering Council of Zimbabwe (ECZ) Firm Registration',
                'obligation_type' => 'REGISTRATION',
                'legal_classification' => 'PROFESSIONAL_REGULATION',
                'verification_status' => 'VERIFIED_OFFICIAL',
                'description' => 'Mandatory professional council registration for practicing consulting engineering firms and design offices.',
                'statutory_citation' => 'Engineering Council Act [Chapter 27:22]',
                'applicability_criteria' => [
                    'industry_sectors' => ['CONSULTING_ENGINEERING', 'CIVIL_DESIGN'],
                    'licensed_profession_involved' => true,
                ],
                'prerequisites' => ['CIPZ_INCORP'],
                'required_documents' => ['CERTIFICATE_OF_INCORPORATION', 'REGISTERED_ENGINEER_PRACTICING_CERTIFICATE'],
                'estimated_processing_days' => 14,
                'renewal_frequency' => 'ANNUAL',
                'is_entity_level' => true,
            ],
            [
                'code' => 'CZI_MEMBERSHIP',
                'authority_id' => $authorityModels['CZI']->id,
                'source_id' => $sourceModels['CZI_CONSTITUTION']->id,
                'rule_pack_id' => null,
                'title' => 'Confederation of Zimbabwe Industries (CZI) Membership',
                'obligation_type' => 'REGISTRATION',
                'legal_classification' => 'OPTIONAL_ASSOCIATION',
                'verification_status' => 'REQUIRES_REVIEW',
                'description' => 'Voluntary trade and industrial association membership for business networking and advocacy. Not required by statute.',
                'statutory_citation' => null,
                'applicability_criteria' => [
                    'is_voluntary_association' => true,
                ],
                'prerequisites' => [],
                'required_documents' => [],
                'estimated_processing_days' => 10,
                'renewal_frequency' => 'ANNUAL',
                'is_entity_level' => true,
            ],
        ];

        foreach ($obligations as $ob) {
            ObligationDefinition::updateOrCreate(
                ['code' => $ob['code']],
                $ob
            );
        }
    }
}
