<?php

namespace App\Domains\RegistrationEngine\Services;

class QuestionnaireProfileMapper
{
    /**
     * Map questionnaire answers into a normalized business profile for deterministic evaluation.
     *
     * @param array<string, mixed> $answers Raw questionnaire answers
     * @return array<string, mixed>
     */
    public function map(array $answers): array
    {
        $entityType = $answers['entity_type'] ?? 'PRIVATE_LIMITED_COMPANY';
        $businessMode = $answers['business_mode'] ?? 'NEW';

        // Incorporation status logic
        $isIncorporated = false;
        if (isset($answers['is_already_incorporated'])) {
            $isIncorporated = (bool) $answers['is_already_incorporated'];
        } elseif ($businessMode === 'EXISTING' || !empty($answers['cobe_registered'])) {
            $isIncorporated = true;
        }

        $companyRegNumber = $answers['company_registration_number'] ?? null;
        if (!$isIncorporated) {
            $incorporationStatus = 'NOT_INCORPORATED';
            $companyRegNumber = null; // Prevent contradiction in RuleConditionEvaluator
        } else {
            $incorporationStatus = 'INCORPORATED';
        }

        // Workforce logic
        $employeeCount = max(0, (int) ($answers['employee_count'] ?? 0));
        $hasEmployees = isset($answers['has_employees'])
            ? (bool) $answers['has_employees']
            : ($employeeCount > 0);

        $paysWages = isset($answers['pays_wages'])
            ? (bool) $answers['pays_wages']
            : $hasEmployees;

        // Prevent contradiction if 0 employees reported
        if ($employeeCount === 0) {
            $hasEmployees = false;
            $paysWages = false;
        }

        // Turnover
        $annualTurnover = max(0.0, (float) ($answers['annual_turnover_usd'] ?? 0.0));
        $wantsVoluntaryVat = (bool) ($answers['wants_voluntary_vat'] ?? false);

        // Premises & Jurisdictions
        $hasPremises = (bool) ($answers['has_physical_premises'] ?? true);
        $primaryJurisdiction = $answers['jurisdiction_code'] ?? 'ZW-HARARE';

        $branchJurisdictions = [];
        if (!empty($answers['branches']) && is_array($answers['branches'])) {
            foreach ($answers['branches'] as $branch) {
                if (!empty($branch['jurisdiction_code'])) {
                    $branchJurisdictions[] = $branch['jurisdiction_code'];
                }
            }
        }
        $allJurisdictions = array_values(array_unique(array_filter([$primaryJurisdiction, ...$branchJurisdictions])));

        // Sector & Activities
        $primarySector = $answers['industry_sector'] ?? 'ENGINEERING';
        $secondarySectors = [];
        if (!empty($answers['secondary_activities'])) {
            $secondarySectors = is_array($answers['secondary_activities'])
                ? $answers['secondary_activities']
                : [$answers['secondary_activities']];
        }
        $allSectors = array_values(array_unique(array_filter([$primarySector, ...$secondarySectors])));

        // Procurement & Association
        $bidsTenders = (bool) ($answers['bids_on_government_tenders'] ?? $answers['bid_on_tenders'] ?? false);
        $isVoluntaryAssoc = (bool) ($answers['wants_czi_membership'] ?? $answers['join_czi'] ?? $answers['is_voluntary_association'] ?? false);

        // Professional Activities
        $licensedProfession = (bool) ($answers['licensed_profession_involved'] ?? $answers['is_engineering_firm'] ?? false);
        // Note: only mark licensed_profession_involved true if explicitly declared or in consulting engineering
        if (in_array('CONSULTING_ENGINEERING', $allSectors, true) || in_array('CIVIL_DESIGN', $allSectors, true)) {
            $licensedProfession = true;
        }

        // Build existing registrations dictionary for extraction
        $existingRegistrations = [];

        if ($isIncorporated && !empty($answers['company_registration_number'])) {
            $existingRegistrations['CIPZ_INCORP'] = [
                'registration_number' => (string) $answers['company_registration_number'],
                'registered_at' => $answers['incorporation_date'] ?? null,
            ];
        }

        $bpNumber = $answers['bp_number'] ?? $answers['tin_number'] ?? null;
        if (!empty($bpNumber)) {
            $existingRegistrations['ZIMRA_TIN_REG'] = [
                'registration_number' => (string) $bpNumber,
            ];
        }

        $vatNumber = $answers['vat_registration_number'] ?? $answers['vat_number'] ?? null;
        if (!empty($vatNumber)) {
            $existingRegistrations['ZIMRA_VAT_REG'] = [
                'registration_number' => (string) $vatNumber,
            ];
        }

        $nssaNumber = $answers['nssa_employer_number'] ?? $answers['nssa_number'] ?? null;
        if (!empty($nssaNumber)) {
            $existingRegistrations['NSSA_EMPLOYER_REG'] = [
                'registration_number' => (string) $nssaNumber,
            ];
        }

        $councilLicence = $answers['council_licence_number'] ?? null;
        if (!empty($councilLicence)) {
            $existingRegistrations['COH_BUSINESS_LICENCE'] = [
                'registration_number' => (string) $councilLicence,
            ];
        }

        $prazNumber = $answers['praz_supplier_number'] ?? null;
        if (!empty($prazNumber)) {
            $existingRegistrations['PRAZ_SUPPLIER_REG'] = [
                'registration_number' => (string) $prazNumber,
            ];
        }

        $necNumber = $answers['nec_registration_number'] ?? null;
        if (!empty($necNumber)) {
            $necInd = $answers['nec_industry'] ?? null;
            if ($necInd === 'NEC_ENG' || in_array('ENGINEERING', $allSectors, true)) {
                $existingRegistrations['NEC_ENG_REG'] = [
                    'registration_number' => (string) $necNumber,
                ];
            }
            if ($necInd === 'NEC_COMM' || in_array('COMMERCIAL_TRADE', $allSectors, true) || in_array('RETAIL', $allSectors, true)) {
                $existingRegistrations['NEC_COMM_REG'] = [
                    'registration_number' => (string) $necNumber,
                ];
            }
        }

        return [
            // Business Identity
            'entity_type' => $entityType,
            'registered_name' => $answers['registered_name'] ?? $answers['legal_name'] ?? 'Zimbabwe Enterprise',
            'trading_name' => $answers['trading_name'] ?? null,
            'business_mode' => $businessMode,
            'is_already_incorporated' => $isIncorporated,
            'incorporation_status' => $incorporationStatus,
            'company_registration_number' => $companyRegNumber,

            // Scale & Tax
            'industry_sector' => $primarySector,
            'industry_sectors' => $allSectors,
            'annual_turnover_usd' => $annualTurnover,
            'turnover_currency' => $answers['turnover_currency'] ?? 'USD',
            'wants_voluntary_vat' => $wantsVoluntaryVat,
            'licensed_profession_involved' => $licensedProfession,

            // Workforce & Payroll
            'employee_count' => $employeeCount,
            'has_employees' => $hasEmployees,
            'pays_wages' => $paysWages,
            'gross_monthly_wage_bill_usd' => isset($answers['gross_monthly_wage_bill_usd']) ? (float) $answers['gross_monthly_wage_bill_usd'] : null,
            'employee_categories' => (array) ($answers['employee_categories'] ?? []),
            'nec_industry' => $answers['nec_industry'] ?? null,
            'is_exempt_diplomatic_or_religious' => (bool) ($answers['is_exempt_diplomatic_or_religious'] ?? false),

            // Premises
            'has_physical_premises' => $hasPremises,
            'premises_type' => $answers['premises_type'] ?? 'COMMERCIAL_OFFICE',
            'jurisdiction_code' => $primaryJurisdiction,
            'jurisdiction_codes' => $allJurisdictions,
            'physical_address' => $answers['physical_address'] ?? null,
            'branches' => (array) ($answers['branches'] ?? []),

            // Procurement & Associations
            'bids_on_government_tenders' => $bidsTenders,
            'intends_to_bid_government_tenders' => $bidsTenders,
            'tender_categories' => (array) ($answers['tender_categories'] ?? []),
            'is_voluntary_association' => $isVoluntaryAssoc,

            // Explicit Declared Registrations map
            'existing_registrations' => $existingRegistrations,
            'raw_answers' => $answers,
        ];
    }
}
