<?php

namespace App\Domains\RegistrationEngine\Services;

use App\Domains\AuthAndTenant\Models\Organisation;
use App\Domains\RegistrationEngine\DTOs\ApplicabilityDecision;
use App\Domains\RegistrationEngine\Models\ApplicabilityAssessment;
use App\Domains\RegistrationEngine\Models\AssessmentResult;
use App\Domains\RegistrationEngine\Models\ObligationDefinition;
use App\Domains\RegistrationEngine\Models\ObligationInstance;
use App\Domains\RegistrationEngine\Models\RegistrationQuestionnaire;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RegistrationRoadmapGenerator
{
    public function __construct(
        protected ApplicabilityEvaluator $evaluator = new ApplicabilityEvaluator(),
        protected DependencyGraph $dependencyGraph = new DependencyGraph()
    ) {
    }

    /**
     * Generate an auditable registration roadmap for an organisation.
     *
     * @param string $tenantId
     * @param string $organisationId
     * @param array<string, mixed> $businessProfile
     * @param CarbonInterface|string|null $assessmentDate
     * @param string|null $questionnaireId
     * @param string|null $assessedByUserId
     * @param string|null $idempotencyKey
     * @param bool $persist
     * @return array{
     *     assessment_id: string|null,
     *     tenant_id: string,
     *     organisation_id: string,
     *     assessed_at: string,
     *     summary: array{
     *         total_evaluated: int,
     *         mandatory_count: int,
     *         conditional_count: int,
     *         exempt_count: int,
     *         not_applicable_count: int,
     *         unverified_hold_count: int,
     *         requires_information_count: int
     *     },
     *     roadmap: array<int, array<string, mixed>>,
     *     exemptions: array<int, array<string, mixed>>,
     *     not_applicable: array<int, array<string, mixed>>,
     *     unresolved: array<int, array<string, mixed>>,
     *     is_idempotent_replay: bool
     * }
     */
    public function generate(
        string $tenantId,
        string $organisationId,
        array $businessProfile,
        CarbonInterface|string|null $assessmentDate = null,
        ?string $questionnaireId = null,
        ?string $assessedByUserId = null,
        ?string $idempotencyKey = null,
        bool $persist = true
    ): array {
        // Validate tenant & organisation
        $organisation = Organisation::where('id', $organisationId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$organisation) {
            throw new InvalidArgumentException("Organisation [{$organisationId}] not found under tenant [{$tenantId}].");
        }

        $evalDate = $assessmentDate ? Carbon::parse($assessmentDate) : Carbon::now();
        $fingerprint = $idempotencyKey ?? hash('sha256', json_encode($businessProfile) . $evalDate->toDateString());

        // Check idempotency if persisting
        if ($persist && $idempotencyKey !== null) {
            $existing = ApplicabilityAssessment::where('tenant_id', $tenantId)
                ->where('organisation_id', $organisationId)
                ->whereJsonContains('evaluation_inputs->idempotency_key', $idempotencyKey)
                ->with(['results.obligationDefinition.authority', 'results.ruleVersion'])
                ->first();

            if ($existing) {
                return $this->formatExistingAssessment($existing, true);
            }
        }

        // 1. Run deterministic rule evaluation
        /** @var Collection<int, ApplicabilityDecision> $decisions */
        $decisions = $this->evaluator->evaluate($businessProfile, $evalDate);

        // 2. Separate decisions by applicability status
        $roadmapDecisions = [];
        $exemptionDecisions = [];
        $notApplicableDecisions = [];
        $unresolvedDecisions = [];

        foreach ($decisions as $decision) {
            if ($decision->decision === 'EXEMPT') {
                $exemptionDecisions[] = $decision;
            } elseif ($decision->decision === 'NOT_APPLICABLE') {
                $notApplicableDecisions[] = $decision;
            } elseif ($decision->decision === 'REQUIRES_INFORMATION') {
                $unresolvedDecisions[] = $decision;
                $roadmapDecisions[] = $decision; // Included in roadmap to highlight missing info steps
            } else {
                $roadmapDecisions[] = $decision;
            }
        }

        // 3. Topologically sort active roadmap obligations via DependencyGraph (Kahn's algorithm)
        $roadmapDefinitions = array_map(fn (ApplicabilityDecision $d) => $d->obligationDefinition, $roadmapDecisions);
        $sortedSequence = $this->dependencyGraph->sort($roadmapDefinitions, strictPrerequisites: false);

        // Map sorted sequences to decisions
        $decisionMap = [];
        foreach ($roadmapDecisions as $d) {
            $decisionMap[$d->obligationDefinition->code] = $d;
        }

        $orderedRoadmapItems = [];
        foreach ($sortedSequence as $item) {
            $code = $item['obligation']->code;
            if (isset($decisionMap[$code])) {
                $orderedRoadmapItems[] = $this->formatRoadmapItem(
                    $decisionMap[$code],
                    $item['sequence'],
                    $item['priority'],
                    $item['hard_prerequisites'],
                    $item['procedural_recommendations']
                );
            }
        }

        // 4. Persistence in transaction if requested
        $assessmentId = null;
        if ($persist) {
            $assessmentId = DB::transaction(function () use (
                $tenantId,
                $organisationId,
                $questionnaireId,
                $assessedByUserId,
                $evalDate,
                $businessProfile,
                $fingerprint,
                $idempotencyKey,
                $decisions
            ) {
                // Ensure valid questionnaire record
                $qId = $questionnaireId;
                if (!$qId) {
                    $q = RegistrationQuestionnaire::firstOrCreate(
                        [
                            'tenant_id' => $tenantId,
                            'organisation_id' => $organisationId,
                            'status' => 'COMPLETED',
                        ],
                        [
                            'current_step' => 5,
                            'answers' => $businessProfile,
                            'completed_at' => Carbon::now(),
                            'completed_by_user_id' => $assessedByUserId,
                        ]
                    );
                    $qId = $q->id;
                } else {
                    $q = RegistrationQuestionnaire::where('id', $qId)
                        ->where('tenant_id', $tenantId)
                        ->first();
                    if (!$q) {
                        throw new InvalidArgumentException("Questionnaire [{$qId}] does not belong to tenant [{$tenantId}].");
                    }
                }

                $assessment = ApplicabilityAssessment::create([
                    'tenant_id' => $tenantId,
                    'organisation_id' => $organisationId,
                    'questionnaire_id' => $qId,
                    'assessment_type' => 'INITIAL_REGISTRATION',
                    'evaluation_inputs' => array_merge($businessProfile, [
                        'idempotency_key' => $idempotencyKey ?? $fingerprint,
                        'assessment_date' => $evalDate->toDateString(),
                    ]),
                    'assessed_by_user_id' => $assessedByUserId,
                    'generated_at' => Carbon::now(),
                ]);

                foreach ($decisions as $decision) {
                    $res = AssessmentResult::create([
                        'tenant_id' => $tenantId,
                        'assessment_id' => $assessment->id,
                        'obligation_definition_id' => $decision->obligationDefinition->id,
                        'rule_version_id' => $decision->ruleVersion?->id,
                        'decision' => $decision->decision,
                        'legal_classification' => $decision->legalClassification,
                        'trigger_reasons' => $decision->triggerReasons,
                        'rule_source_citation' => $decision->sourceCitation,
                        'prerequisites_snapshot' => $decision->obligationDefinition->prerequisites,
                        'required_documents_snapshot' => $decision->obligationDefinition->required_documents,
                        'evaluated_at' => Carbon::now(),
                    ]);

                    // Sync obligation instance tracking (preserve historical immutability on assessment_results)
                    $this->syncObligationInstance($tenantId, $organisationId, $decision, $res->id);
                }

                return $assessment->id;
            });
        }

        // 5. Build response payload
        return [
            'assessment_id' => $assessmentId,
            'tenant_id' => $tenantId,
            'organisation_id' => $organisationId,
            'assessed_at' => $evalDate->toIso8601String(),
            'summary' => [
                'total_evaluated' => $decisions->count(),
                'mandatory_count' => $decisions->where('decision', 'MANDATORY')->count(),
                'conditional_count' => $decisions->where('decision', 'CONDITIONAL')->count(),
                'exempt_count' => $decisions->where('decision', 'EXEMPT')->count(),
                'not_applicable_count' => $decisions->where('decision', 'NOT_APPLICABLE')->count(),
                'unverified_hold_count' => $decisions->where('decision', 'UNVERIFIED_HOLD')->count(),
                'requires_information_count' => $decisions->where('decision', 'REQUIRES_INFORMATION')->count(),
            ],
            'roadmap' => $orderedRoadmapItems,
            'exemptions' => array_map(fn (ApplicabilityDecision $d) => $d->toArray(), $exemptionDecisions),
            'not_applicable' => array_map(fn (ApplicabilityDecision $d) => $d->toArray(), $notApplicableDecisions),
            'unresolved' => array_map(fn (ApplicabilityDecision $d) => $d->toArray(), $unresolvedDecisions),
            'is_idempotent_replay' => false,
        ];
    }

    /**
     * Format an individual roadmap item with complete traceability.
     *
     * @param ApplicabilityDecision $decision
     * @param int $sequence
     * @param int $priority
     * @param array<int, string> $hardPrerequisites
     * @param array<int, string> $proceduralRecommendations
     * @return array<string, mixed>
     */
    protected function formatRoadmapItem(
        ApplicabilityDecision $decision,
        int $sequence,
        int $priority,
        array $hardPrerequisites,
        array $proceduralRecommendations
    ): array {
        $def = $decision->obligationDefinition;
        $rule = $decision->ruleVersion;

        return [
            'sequence_number' => $sequence,
            'priority' => $priority,
            'obligation_code' => $def->code,
            'title' => $def->title,
            'authority' => [
                'code' => $def->authority?->code,
                'name' => $def->authority?->name,
                'category' => $def->authority?->category,
                'portal_url' => $def->authority?->portal_url,
            ],
            'legal_classification' => $decision->legalClassification,
            'applicability_decision' => $decision->decision,
            'required_prerequisites' => $hardPrerequisites,
            'procedural_recommendations' => $proceduralRecommendations,
            'missing_information' => $decision->unresolvedInformation,
            'source_citation' => $decision->sourceCitation,
            'evidence_requirements' => [
                'required_documents' => $def->required_documents ?? [],
                'is_entity_level' => $def->is_entity_level,
                'renewal_frequency' => $def->renewal_frequency,
            ],
            'verification_status' => $rule ? $rule->verification_status : 'UNVERIFIED',
            'applicable_rule_version' => $rule ? [
                'id' => $rule->id,
                'version_code' => $rule->version_code,
                'effective_from' => $rule->effective_from?->toDateString(),
                'effective_to' => $rule->effective_to?->toDateString(),
                'status' => $rule->status,
                'verification_status' => $rule->verification_status,
            ] : null,
            'trigger_reasons' => $decision->triggerReasons,
            'declared_registration' => $decision->declaredRegistration,
            'estimated_processing_days' => $def->estimated_processing_days,
        ];
    }

    /**
     * Synchronize organisation obligation instance tracking without corrupting historical assessment results.
     */
    protected function syncObligationInstance(
        string $tenantId,
        string $organisationId,
        ApplicabilityDecision $decision,
        string $resultId
    ): void {
        $instance = ObligationInstance::where('organisation_id', $organisationId)
            ->where('definition_id', $decision->obligationDefinition->id)
            ->first();

        $declared = $decision->declaredRegistration;
        $hasDeclared = !empty($declared['declared_number']);

        // IMPORTANT (Requirement 7): Do NOT label COMPLIANT solely because user declared a registration
        $status = 'NOT_STARTED';
        $evidenceStatus = 'UNKNOWN';

        if ($decision->decision === 'EXEMPT') {
            $status = 'EXEMPT';
            $evidenceStatus = 'USER_DECLARED';
        } elseif ($hasDeclared) {
            $status = 'AWAITING_VERIFICATION';
            $evidenceStatus = 'USER_DECLARED';
        }

        if ($instance) {
            $instance->update([
                'assessment_result_id' => $resultId,
                'status' => $status,
                'evidence_status' => $evidenceStatus,
                'declared_registration_number' => $declared['declared_number'] ?? $instance->declared_registration_number,
                'declared_registered_at' => $declared['declared_date'] ?? $instance->declared_registered_at,
            ]);
        } else {
            ObligationInstance::create([
                'tenant_id' => $tenantId,
                'organisation_id' => $organisationId,
                'definition_id' => $decision->obligationDefinition->id,
                'assessment_result_id' => $resultId,
                'status' => $status,
                'evidence_status' => $evidenceStatus,
                'declared_registration_number' => $declared['declared_number'] ?? null,
                'declared_registered_at' => $declared['declared_date'] ?? null,
            ]);
        }
    }

    /**
     * Format a previously persisted assessment for idempotent replay.
     *
     * @param ApplicabilityAssessment $assessment
     * @param bool $isIdempotentReplay
     * @return array<string, mixed>
     */
    protected function formatExistingAssessment(ApplicabilityAssessment $assessment, bool $isIdempotentReplay): array
    {
        $results = $assessment->results;
        $roadmap = [];
        $exemptions = [];
        $notApplicable = [];
        $unresolved = [];

        $seq = 1;
        foreach ($results as $res) {
            $def = $res->obligationDefinition;
            $rule = $res->ruleVersion;

            $item = [
                'sequence_number' => $seq++,
                'priority' => DependencyGraph::CLASSIFICATION_PRIORITY[$res->legal_classification] ?? 99,
                'obligation_code' => $def?->code,
                'title' => $def?->title,
                'authority' => [
                    'code' => $def?->authority?->code,
                    'name' => $def?->authority?->name,
                ],
                'legal_classification' => $res->legal_classification,
                'applicability_decision' => $res->decision,
                'required_prerequisites' => $res->prerequisites_snapshot ?? [],
                'procedural_recommendations' => [],
                'missing_information' => [],
                'source_citation' => $res->rule_source_citation,
                'evidence_requirements' => [
                    'required_documents' => $res->required_documents_snapshot ?? [],
                    'is_entity_level' => $def?->is_entity_level ?? true,
                ],
                'verification_status' => $rule?->verification_status ?? 'UNVERIFIED',
                'applicable_rule_version' => $rule ? [
                    'id' => $rule->id,
                    'version_code' => $rule->version_code,
                    'status' => $rule->status,
                ] : null,
                'trigger_reasons' => $res->trigger_reasons ?? [],
                'declared_registration' => null,
                'estimated_processing_days' => $def?->estimated_processing_days,
            ];

            if ($res->decision === 'EXEMPT') {
                $exemptions[] = $item;
            } elseif ($res->decision === 'NOT_APPLICABLE') {
                $notApplicable[] = $item;
            } elseif ($res->decision === 'REQUIRES_INFORMATION') {
                $unresolved[] = $item;
                $roadmap[] = $item;
            } else {
                $roadmap[] = $item;
            }
        }

        return [
            'assessment_id' => $assessment->id,
            'tenant_id' => $assessment->tenant_id,
            'organisation_id' => $assessment->organisation_id,
            'assessed_at' => $assessment->generated_at->toIso8601String(),
            'summary' => [
                'total_evaluated' => $results->count(),
                'mandatory_count' => $results->where('decision', 'MANDATORY')->count(),
                'conditional_count' => $results->where('decision', 'CONDITIONAL')->count(),
                'exempt_count' => $results->where('decision', 'EXEMPT')->count(),
                'not_applicable_count' => $results->where('decision', 'NOT_APPLICABLE')->count(),
                'unverified_hold_count' => $results->where('decision', 'UNVERIFIED_HOLD')->count(),
                'requires_information_count' => $results->where('decision', 'REQUIRES_INFORMATION')->count(),
            ],
            'roadmap' => $roadmap,
            'exemptions' => $exemptions,
            'not_applicable' => $notApplicable,
            'unresolved' => $unresolved,
            'is_idempotent_replay' => $isIdempotentReplay,
        ];
    }
}
