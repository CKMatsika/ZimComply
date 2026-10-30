<?php

namespace App\Domains\RegistrationEngine\DTOs;

use App\Domains\RegistrationEngine\Models\ObligationDefinition;
use App\Domains\RegistrationEngine\Models\RuleVersion;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Support\Arrayable;

class ApplicabilityDecision implements Arrayable
{
    /**
     * @param ObligationDefinition $obligationDefinition
     * @param RuleVersion|null $ruleVersion
     * @param string $decision MANDATORY, CONDITIONAL, EXEMPT, NOT_APPLICABLE, UNVERIFIED_HOLD, REQUIRES_INFORMATION
     * @param string $legalClassification
     * @param array<int, string> $conditionsEvaluated
     * @param array<int, string> $triggerReasons
     * @param string|null $sourceCitation
     * @param CarbonInterface $assessedAt
     * @param array<int, string> $unresolvedInformation
     * @param array<string, mixed>|null $declaredRegistration
     */
    public function __construct(
        public ObligationDefinition $obligationDefinition,
        public ?RuleVersion $ruleVersion,
        public string $decision,
        public string $legalClassification,
        public array $conditionsEvaluated,
        public array $triggerReasons,
        public ?string $sourceCitation,
        public CarbonInterface $assessedAt,
        public array $unresolvedInformation = [],
        public ?array $declaredRegistration = null,
    ) {
    }

    public function isDefinitive(): bool
    {
        return in_array($this->decision, ['MANDATORY', 'CONDITIONAL', 'EXEMPT'], true);
    }

    public function isApplicable(): bool
    {
        return in_array($this->decision, ['MANDATORY', 'CONDITIONAL'], true);
    }

    public function requiresInformation(): bool
    {
        return $this->decision === 'REQUIRES_INFORMATION';
    }

    public function isUnverifiedHold(): bool
    {
        return $this->decision === 'UNVERIFIED_HOLD';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'obligation_code' => $this->obligationDefinition->code,
            'title' => $this->obligationDefinition->title,
            'regulatory_authority_code' => $this->obligationDefinition->authority?->code,
            'regulatory_authority_name' => $this->obligationDefinition->authority?->name,
            'legal_classification' => $this->legalClassification,
            'decision' => $this->decision,
            'rule_version_id' => $this->ruleVersion?->id,
            'rule_version_code' => $this->ruleVersion?->version_code,
            'rule_source_citation' => $this->sourceCitation,
            'conditions_evaluated' => $this->conditionsEvaluated,
            'trigger_reasons' => $this->triggerReasons,
            'unresolved_information' => $this->unresolvedInformation,
            'assessed_at' => $this->assessedAt->toIso8601String(),
            'declared_registration' => $this->declaredRegistration,
            'prerequisites' => $this->obligationDefinition->prerequisites ?? [],
            'required_documents' => $this->obligationDefinition->required_documents ?? [],
            'estimated_processing_days' => $this->obligationDefinition->estimated_processing_days,
            'renewal_frequency' => $this->obligationDefinition->renewal_frequency,
        ];
    }
}
