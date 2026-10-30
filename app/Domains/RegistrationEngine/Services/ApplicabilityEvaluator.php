<?php

namespace App\Domains\RegistrationEngine\Services;

use App\Domains\RegistrationEngine\DTOs\ApplicabilityDecision;
use App\Domains\RegistrationEngine\Models\ObligationDefinition;
use App\Domains\RegistrationEngine\Models\RuleVersion;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class ApplicabilityEvaluator
{
    public function __construct(
        protected RuleConditionEvaluator $conditionEvaluator = new RuleConditionEvaluator()
    ) {
    }

    /**
     * Deterministically evaluate regulatory applicability for a business profile on an assessment date.
     * ZERO AI API calls are made during this evaluation.
     *
     * @param array<string, mixed> $businessProfile Validated business profile inputs
     * @param CarbonInterface|string|null $assessmentDate Effective date of assessment
     * @param Collection<int, ObligationDefinition>|null $definitions Optional subset of definitions to evaluate
     * @return Collection<int, ApplicabilityDecision>
     */
    public function evaluate(
        array $businessProfile,
        CarbonInterface|string|null $assessmentDate = null,
        ?Collection $definitions = null
    ): Collection {
        $evalDate = $this->resolveAssessmentDate($assessmentDate);

        $targetDefinitions = $definitions ?? ObligationDefinition::query()
            ->with(['authority', 'source', 'rulePack.versions'])
            ->where('is_active', true)
            ->get();

        $decisions = collect();

        foreach ($targetDefinitions as $def) {
            $decisions->push($this->evaluateObligation($def, $businessProfile, $evalDate));
        }

        return $decisions;
    }

    /**
     * Evaluate a single obligation definition against the profile.
     */
    public function evaluateObligation(
        ObligationDefinition $definition,
        array $profile,
        CarbonInterface $assessmentDate
    ): ApplicabilityDecision {
        // 1. Resolve approved, verified, effective-dated rule version
        $ruleVersion = $this->resolveEffectiveRuleVersion($definition, $assessmentDate);

        // If no verified active rule version is effective for this obligation on assessment date:
        // Must NOT produce a definitive statutory decision (MANDATORY/CONDITIONAL/EXEMPT)
        if (!$ruleVersion) {
            return new ApplicabilityDecision(
                obligationDefinition: $definition,
                ruleVersion: null,
                decision: 'UNVERIFIED_HOLD',
                legalClassification: $definition->legal_classification,
                conditionsEvaluated: ["Rule version audit: No approved, active, official rule version found for date [{$assessmentDate->toDateString()}]."],
                triggerReasons: ["Obligation [{$definition->code}] placed on UNVERIFIED_HOLD pending gazetted source review and rule pack approval."],
                sourceCitation: $definition->statutory_citation ?? $definition->source?->citation,
                assessedAt: $assessmentDate,
                unresolvedInformation: ['rule_version_unverified'],
                declaredRegistration: $this->extractDeclaredRegistration($definition->code, $profile),
            );
        }

        $ruleParameters = (array) ($ruleVersion->parameters ?? []);
        $criteria = (array) ($definition->applicability_criteria ?? []);

        // 2. Check for statutory exemptions
        $exemptionReason = $this->checkStatutoryExemption($definition, $profile, $ruleParameters);
        if ($exemptionReason !== null) {
            return new ApplicabilityDecision(
                obligationDefinition: $definition,
                ruleVersion: $ruleVersion,
                decision: 'EXEMPT',
                legalClassification: $definition->legal_classification,
                conditionsEvaluated: ["Statutory exemption check: {$exemptionReason}"],
                triggerReasons: [$exemptionReason],
                sourceCitation: $ruleVersion->source?->citation ?? $definition->statutory_citation,
                assessedAt: $assessmentDate,
                unresolvedInformation: [],
                declaredRegistration: $this->extractDeclaredRegistration($definition->code, $profile),
            );
        }

        // 3. Evaluate criteria conditions via deterministic RuleConditionEvaluator
        $evaluation = $this->conditionEvaluator->evaluate($criteria, $profile, $ruleParameters);

        // 4. Handle unresolved information or contradictory inputs
        if ($evaluation['status'] === 'REQUIRES_INFORMATION') {
            return new ApplicabilityDecision(
                obligationDefinition: $definition,
                ruleVersion: $ruleVersion,
                decision: 'REQUIRES_INFORMATION',
                legalClassification: $definition->legal_classification,
                conditionsEvaluated: $evaluation['evaluated_conditions'],
                triggerReasons: $evaluation['trigger_reasons'],
                sourceCitation: $ruleVersion->source?->citation ?? $definition->statutory_citation,
                assessedAt: $assessmentDate,
                unresolvedInformation: $evaluation['unresolved_fields'],
                declaredRegistration: $this->extractDeclaredRegistration($definition->code, $profile),
            );
        }

        // 5. Handle not applicable
        if (!$evaluation['matched']) {
            return new ApplicabilityDecision(
                obligationDefinition: $definition,
                ruleVersion: $ruleVersion,
                decision: 'NOT_APPLICABLE',
                legalClassification: $definition->legal_classification,
                conditionsEvaluated: $evaluation['evaluated_conditions'],
                triggerReasons: ['Entity profile does not meet statutory criteria for this obligation.'],
                sourceCitation: $ruleVersion->source?->citation ?? $definition->statutory_citation,
                assessedAt: $assessmentDate,
                unresolvedInformation: [],
                declaredRegistration: $this->extractDeclaredRegistration($definition->code, $profile),
            );
        }

        // 6. Handle matched criteria -> determine statutory classification
        $decisionType = $this->resolveApplicableDecisionType($definition);

        return new ApplicabilityDecision(
            obligationDefinition: $definition,
            ruleVersion: $ruleVersion,
            decision: $decisionType,
            legalClassification: $definition->legal_classification,
            conditionsEvaluated: $evaluation['evaluated_conditions'],
            triggerReasons: $evaluation['trigger_reasons'],
            sourceCitation: $ruleVersion->source?->citation ?? $definition->statutory_citation,
            assessedAt: $assessmentDate,
            unresolvedInformation: [],
            declaredRegistration: $this->extractDeclaredRegistration($definition->code, $profile),
        );
    }

    /**
     * Resolve the active, approved, verified rule version for an obligation on a given date.
     */
    public function resolveEffectiveRuleVersion(
        ObligationDefinition $definition,
        CarbonInterface $assessmentDate
    ): ?RuleVersion {
        if (!$definition->rule_pack_id) {
            return null;
        }

        $dateString = $assessmentDate->toDateString();

        return RuleVersion::query()
            ->with('source')
            ->where('rule_pack_id', $definition->rule_pack_id)
            ->where('status', 'ACTIVE')
            ->where('verification_status', 'VERIFIED_OFFICIAL')
            ->where('effective_from', '<=', $dateString)
            ->where(function ($q) use ($dateString) {
                $q->whereNull('effective_to')
                  ->orWhere('effective_to', '>=', $dateString);
            })
            ->orderByDesc('effective_from')
            ->first();
    }

    /**
     * Check if business profile qualifies for a legal exemption from this obligation.
     */
    protected function checkStatutoryExemption(
        ObligationDefinition $definition,
        array $profile,
        array $ruleParameters
    ): ?string {
        // Exemption check based on rule parameters (e.g. ZIMDEF statutory exemptions)
        if (isset($ruleParameters['statutory_exemptions']) && is_array($ruleParameters['statutory_exemptions'])) {
            $exemptions = $ruleParameters['statutory_exemptions'];

            if (in_array('DIPLOMATIC', $exemptions, true) && !empty($profile['is_diplomatic_mission'])) {
                return 'Statutory exemption: Diplomatic missions are exempt under the enabling Act.';
            }

            if (in_array('RELIGIOUS_CHARITABLE', $exemptions, true) && !empty($profile['is_religious_or_charity'])) {
                return 'Statutory exemption: Religious and registered charitable institutions are exempt from training levy.';
            }

            if (in_array('EDUCATIONAL', $exemptions, true) && !empty($profile['is_educational_institution'])) {
                return 'Statutory exemption: Qualifying non-commercial educational institutions are exempt.';
            }
        }

        // Explicit declared statutory exemption in profile
        if (!empty($profile['statutory_exemptions'][$definition->code])) {
            return 'Statutory exemption declared: ' . (string) $profile['statutory_exemptions'][$definition->code];
        }

        return null;
    }

    /**
     * Resolve decision type based on legal classification.
     */
    protected function resolveApplicableDecisionType(ObligationDefinition $definition): string
    {
        return match ($definition->legal_classification) {
            'MANDATORY_STATUTORY' => 'MANDATORY',
            'CONDITIONAL_STATUTORY' => 'CONDITIONAL',
            'CBA_COLLECTIVE_AGREEMENT' => 'CONDITIONAL',
            'PROFESSIONAL_REGULATION' => 'CONDITIONAL',
            'TENDER_SPECIFIC' => 'CONDITIONAL',
            'OPTIONAL_ASSOCIATION' => 'NOT_APPLICABLE',
            default => 'CONDITIONAL',
        };
    }

    /**
     * Extract user-declared registration details without assuming compliance.
     *
     * @param string $obligationCode
     * @param array<string, mixed> $profile
     * @return array<string, mixed>|null
     */
    protected function extractDeclaredRegistration(string $obligationCode, array $profile): ?array
    {
        $existing = $profile['existing_registrations'] ?? $profile['declared_registrations'] ?? [];

        if (isset($existing[$obligationCode]) && is_array($existing[$obligationCode])) {
            return [
                'declared_number' => $existing[$obligationCode]['registration_number'] ?? null,
                'declared_date' => $existing[$obligationCode]['registered_at'] ?? null,
                'verification_state' => 'USER_DECLARED_UNVERIFIED',
            ];
        }

        return null;
    }

    protected function resolveAssessmentDate(CarbonInterface|string|null $date): CarbonInterface
    {
        if ($date === null) {
            return Carbon::now();
        }

        if ($date instanceof CarbonInterface) {
            return $date;
        }

        return Carbon::parse($date);
    }
}
