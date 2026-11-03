<?php

namespace App\Domains\ComplianceEngine\Services;

use App\Domains\AuthAndTenant\Models\AuditEvent;
use App\Domains\AuthAndTenant\Models\Membership;
use App\Domains\AuthAndTenant\Models\Organisation;
use App\Domains\ComplianceEngine\Exceptions\ConcurrentUpdateException;
use App\Domains\ComplianceEngine\Exceptions\InvalidLifecycleTransitionException;
use App\Domains\ComplianceEngine\Models\RenewalActivityLog;
use App\Domains\ComplianceEngine\Models\RenewalAttempt;
use App\Domains\ComplianceEngine\Models\RenewalEvidenceLink;
use App\Domains\ComplianceEngine\Models\StatutoryInstrument;
use App\Domains\RegistrationEngine\Models\ComplianceDocument;
use App\Domains\RegistrationEngine\Models\ObligationDefinition;
use App\Domains\RegistrationEngine\Models\ObligationInstance;
use App\Domains\RegistrationEngine\Models\RegulatoryAuthority;
use App\Domains\RegistrationEngine\Models\RuleVersion;
use App\Domains\RegistrationEngine\Services\ObligationEvidenceStatusEvaluator;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RenewalManagementService
{
    public const SUPPORTED_CATEGORIES = [
        'LICENCE' => 'Operating / Trading Licence',
        'CERTIFICATE' => 'Statutory Certificate',
        'PERMIT' => 'Regulatory Permit',
        'REGISTRATION' => 'Annual / Periodic Registration',
        'CLEARANCE' => 'Tax / Social Security Clearance',
    ];

    public function __construct(
        protected RenewalLifecycleStateMachine $stateMachine = new RenewalLifecycleStateMachine(),
        protected RenewalDeadlineCalculator $deadlineCalculator = new RenewalDeadlineCalculator(),
        protected ?ObligationEvidenceStatusEvaluator $evidenceStatusEvaluator = null
    ) {
        $this->evidenceStatusEvaluator = $evidenceStatusEvaluator ?? app(ObligationEvidenceStatusEvaluator::class);
    }

    /**
     * Retrieve the organisation-specific renewal register, metrics, and filter options.
     *
     * @param Organisation $organisation
     * @param array<string, mixed> $filters
     * @param CarbonInterface|string|null $referenceDate
     * @return array<string, mixed>
     */
    public function getRegisterForOrganisation(
        Organisation $organisation,
        array $filters = [],
        CarbonInterface|string|null $referenceDate = null
    ): array {
        $refDate = $referenceDate
            ? Carbon::parse($referenceDate, RenewalDeadlineCalculator::TIMEZONE)->startOfDay()
            : Carbon::now(RenewalDeadlineCalculator::TIMEZONE)->startOfDay();

        // Synchronize expiry validity states as of reference date
        $this->recalculateExpirationsForOrganisation($organisation, $refDate);

        $query = StatutoryInstrument::withoutGlobalScopes()
            ->where('tenant_id', $organisation->tenant_id)
            ->where('organisation_id', $organisation->id)
            ->with([
                'authority',
                'obligationInstance.definition',
                'obligationDefinition',
                'ruleVersion.source',
                'currentDocument',
                'assignedTo',
                'activeRenewalAttempt.replacementDocument',
                'activeRenewalAttempt.assignedTo',
                'renewalAttempts',
                'evidenceLinks.document',
            ]);

        if (!empty($filters['category']) && $filters['category'] !== 'ALL') {
            $query->where('instrument_category', strtoupper((string) $filters['category']));
        }

        if (!empty($filters['authority'])) {
            $authFilter = $filters['authority'];
            $query->where(function (Builder $q) use ($authFilter) {
                $q->where('regulatory_authority_id', $authFilter)
                  ->orWhereRaw('LOWER(issuing_authority_name) LIKE ?', ['%' . strtolower((string) $authFilter) . '%'])
                  ->orWhereHas('authority', function (Builder $sub) use ($authFilter) {
                      $sub->where('code', $authFilter)->orWhere('id', $authFilter);
                  });
            });
        }

        if (!empty($filters['validity_status']) && $filters['validity_status'] !== 'ALL') {
            $query->where('validity_status', strtoupper((string) $filters['validity_status']));
        }

        if (!empty($filters['workflow_status']) && $filters['workflow_status'] !== 'ALL') {
            $query->where('current_workflow_status', strtoupper((string) $filters['workflow_status']));
        }

        if (!empty($filters['responsible_user'])) {
            if ($filters['responsible_user'] === 'unassigned') {
                $query->whereNull('assigned_to_user_id');
            } else {
                $query->where('assigned_to_user_id', $filters['responsible_user']);
            }
        }

        if (!empty($filters['search'])) {
            $term = '%' . strtolower((string) $filters['search']) . '%';
            $query->where(function (Builder $q) use ($term) {
                $q->whereRaw('LOWER(title) LIKE ?', [$term])
                  ->orWhereRaw('LOWER(reference_number) LIKE ?', [$term])
                  ->orWhereRaw('LOWER(issuing_authority_name) LIKE ?', [$term])
                  ->orWhereRaw('LOWER(premises_or_scope) LIKE ?', [$term]);
            });
        }

        $instruments = $query
            ->orderByRaw('CASE WHEN expiry_date IS NULL THEN 1 ELSE 0 END ASC')
            ->orderBy('expiry_date', 'asc')
            ->orderBy('title', 'asc')
            ->get();

        // Compute full metrics across all organisation instruments
        $allInstruments = StatutoryInstrument::withoutGlobalScopes()
            ->where('tenant_id', $organisation->tenant_id)
            ->where('organisation_id', $organisation->id)
            ->with(['ruleVersion.source', 'obligationDefinition', 'activeRenewalAttempt'])
            ->get();

        $metrics = [
            'total' => 0,
            'active_instruments' => 0,
            'expiring_soon' => 0,
            'expired_instruments' => 0,
            'renewals_in_progress' => 0,
            'awaiting_authority_decision' => 0,
            'completed_renewals' => 0,
            'suspended_or_cancelled' => 0,
            'deadline_unverified' => 0,
        ];

        foreach ($allInstruments as $inst) {
            $metrics['total']++;
            $calc = $this->deadlineCalculator->calculate(
                instrumentDates: [
                    'issue_date' => $inst->issue_date,
                    'expiry_date' => $inst->expiry_date,
                    'source_citation' => $inst->source_citation,
                ],
                ruleVersion: $inst->ruleVersion,
                definition: $inst->obligationDefinition,
                referenceDate: $refDate
            );

            $validity = $this->stateMachine->evaluateInstrumentValidity($inst, $refDate);

            if ($validity === RenewalLifecycleStateMachine::VALIDITY_ACTIVE) {
                $metrics['active_instruments']++;
            } elseif ($validity === RenewalLifecycleStateMachine::VALIDITY_EXPIRED) {
                $metrics['expired_instruments']++;
            } elseif (in_array($validity, [
                RenewalLifecycleStateMachine::VALIDITY_SUSPENDED,
                RenewalLifecycleStateMachine::VALIDITY_CANCELLED,
            ], true)) {
                $metrics['suspended_or_cancelled']++;
            }

            if ($calc['is_expiring_soon'] && $validity === RenewalLifecycleStateMachine::VALIDITY_ACTIVE) {
                $metrics['expiring_soon']++;
            }

            if ($calc['deadline_verification_status'] !== 'VERIFIED_OFFICIAL') {
                $metrics['deadline_unverified']++;
            }

            $wf = $inst->current_workflow_status;
            if (in_array($wf, [
                RenewalLifecycleStateMachine::WORKFLOW_RENEWAL_UPCOMING,
                RenewalLifecycleStateMachine::WORKFLOW_RENEWAL_IN_PROGRESS,
                RenewalLifecycleStateMachine::WORKFLOW_AWAITING_DOCUMENTS,
                RenewalLifecycleStateMachine::WORKFLOW_READY_FOR_SUBMISSION,
            ], true)) {
                $metrics['renewals_in_progress']++;
            } elseif (in_array($wf, [
                RenewalLifecycleStateMachine::WORKFLOW_DECLARED_SUBMITTED,
                RenewalLifecycleStateMachine::WORKFLOW_AWAITING_AUTHORITY_DECISION,
            ], true)) {
                $metrics['awaiting_authority_decision']++;
            } elseif ($wf === RenewalLifecycleStateMachine::WORKFLOW_RENEWED) {
                $metrics['completed_renewals']++;
            }
        }

        $formatted = $instruments->map(fn (StatutoryInstrument $inst) => $this->formatInstrumentItem($inst, $refDate));

        // Optional tab filter on formatted collection
        if (!empty($filters['tab']) && $filters['tab'] !== 'ALL') {
            $tab = strtoupper((string) $filters['tab']);
            $formatted = $formatted->filter(function (array $item) use ($tab) {
                return match ($tab) {
                    'ACTIVE' => $item['validity_status'] === 'ACTIVE',
                    'EXPIRING_SOON' => $item['deadline_metrics']['is_expiring_soon'] === true && $item['validity_status'] === 'ACTIVE',
                    'EXPIRED' => $item['validity_status'] === 'EXPIRED',
                    'IN_PROGRESS' => in_array($item['current_workflow_status'], [
                        'RENEWAL_UPCOMING',
                        'RENEWAL_IN_PROGRESS',
                        'AWAITING_DOCUMENTS',
                        'READY_FOR_SUBMISSION',
                    ], true),
                    'AWAITING_DECISION' => in_array($item['current_workflow_status'], [
                        'DECLARED_SUBMITTED',
                        'AWAITING_AUTHORITY_DECISION',
                    ], true),
                    'COMPLETED' => $item['current_workflow_status'] === 'RENEWED',
                    default => true,
                };
            })->values();
        }

        $members = Membership::where('organisation_id', $organisation->id)
            ->where('status', 'ACTIVE')
            ->with('user')
            ->get()
            ->filter(fn (Membership $m) => $m->user !== null)
            ->map(fn (Membership $m) => [
                'id' => $m->user->id,
                'name' => $m->user->name,
                'email' => $m->user->email,
                'role' => $m->role,
            ])
            ->values();

        return [
            'instruments' => $formatted->values()->all(),
            'metrics' => $metrics,
            'members' => $members,
            'filters' => $filters,
            'reference_date' => $refDate->toDateString(),
            'timezone' => RenewalDeadlineCalculator::TIMEZONE,
        ];
    }

    /**
     * Format a StatutoryInstrument for list and drawer display.
     *
     * @return array<string, mixed>
     */
    public function formatInstrumentItem(
        StatutoryInstrument $instrument,
        CarbonInterface|string|null $referenceDate = null
    ): array {
        $refDate = $referenceDate
            ? Carbon::parse($referenceDate, RenewalDeadlineCalculator::TIMEZONE)->startOfDay()
            : Carbon::now(RenewalDeadlineCalculator::TIMEZONE)->startOfDay();

        $calc = $this->deadlineCalculator->calculate(
            instrumentDates: [
                'issue_date' => $instrument->issue_date,
                'expiry_date' => $instrument->expiry_date,
                'source_citation' => $instrument->source_citation,
            ],
            ruleVersion: $instrument->ruleVersion,
            definition: $instrument->obligationDefinition,
            referenceDate: $refDate
        );

        $validityStatus = $this->stateMachine->evaluateInstrumentValidity($instrument, $refDate);
        if ($validityStatus !== $instrument->validity_status) {
            $instrument->update(['validity_status' => $validityStatus]);
        }

        $activeAttempt = $instrument->activeRenewalAttempt;

        return [
            'id' => $instrument->id,
            'tenant_id' => $instrument->tenant_id,
            'organisation_id' => $instrument->organisation_id,
            'obligation_instance_id' => $instrument->obligation_instance_id,
            'obligation_code' => $instrument->obligationDefinition?->code ?? $instrument->obligationInstance?->definition?->code,
            'instrument_category' => $instrument->instrument_category,
            'instrument_category_label' => self::SUPPORTED_CATEGORIES[$instrument->instrument_category] ?? $instrument->instrument_category,
            'title' => $instrument->title,
            'issuing_authority_name' => $instrument->issuing_authority_name,
            'authority_code' => $instrument->authority?->code,
            'reference_number' => $instrument->reference_number,
            'premises_or_scope' => $instrument->premises_or_scope,
            'issue_date' => $instrument->issue_date?->toDateString(),
            'expiry_date' => $instrument->expiry_date?->toDateString(),
            'renewal_window_opens_at' => $calc['renewal_window_opens_at'],
            'statutory_renewal_deadline' => $calc['statutory_renewal_deadline'],
            'deadline_verification_status' => $calc['deadline_verification_status'],
            'deadline_calculation_basis' => $calc['deadline_calculation_basis'],
            'deadline_metrics' => [
                'days_remaining' => $calc['days_remaining'],
                'days_until_renewal_deadline' => $calc['days_until_renewal_deadline'],
                'is_expired' => $calc['is_expired'],
                'is_expiring_soon' => $calc['is_expiring_soon'],
                'is_renewal_window_open' => $calc['is_renewal_window_open'],
                'is_renewal_deadline_overdue' => $calc['is_renewal_deadline_overdue'],
            ],
            'validity_status' => $validityStatus,
            'validity_status_label' => RenewalLifecycleStateMachine::VALIDITY_STATUSES[$validityStatus] ?? $validityStatus,
            'current_workflow_status' => $instrument->current_workflow_status,
            'current_workflow_status_label' => RenewalLifecycleStateMachine::WORKFLOW_STATUSES[$instrument->current_workflow_status] ?? $instrument->current_workflow_status,
            'source_citation' => $calc['source_citation'],
            'rule_version_code' => $calc['rule_version_code'],
            'responsible_user' => $instrument->assignedTo ? [
                'id' => $instrument->assignedTo->id,
                'name' => $instrument->assignedTo->name,
                'email' => $instrument->assignedTo->email,
            ] : null,
            'current_document' => $instrument->currentDocument ? [
                'id' => $instrument->currentDocument->id,
                'title' => $instrument->currentDocument->title,
                'file_name' => $instrument->currentDocument->file_name,
                'verification_status' => $instrument->currentDocument->verification_status,
                'sha256_hash' => $instrument->currentDocument->sha256_hash,
                'issue_date' => $instrument->currentDocument->issue_date?->toDateString(),
                'expiry_date' => $instrument->currentDocument->expiry_date?->toDateString(),
            ] : null,
            'active_attempt' => $activeAttempt ? [
                'id' => $activeAttempt->id,
                'attempt_number' => $activeAttempt->attempt_number,
                'workflow_status' => $activeAttempt->workflow_status,
                'workflow_status_label' => RenewalLifecycleStateMachine::WORKFLOW_STATUSES[$activeAttempt->workflow_status] ?? $activeAttempt->workflow_status,
                'submission_reference' => $activeAttempt->submission_reference,
                'authority_receipt_number' => $activeAttempt->authority_receipt_number,
                'submitted_at' => $activeAttempt->submitted_at?->toIso8601String(),
                'lock_version' => $activeAttempt->lock_version,
            ] : null,
            'attempts_count' => $instrument->renewalAttempts->count(),
            'evidence_count' => $instrument->evidenceLinks->count(),
            'suspension_or_cancellation_reason' => $instrument->suspension_or_cancellation_reason,
            'notes' => $instrument->notes,
            'metadata_version' => $instrument->metadata_version,
            'lock_version' => $instrument->lock_version,
        ];
    }

    /**
     * Get comprehensive detail for a single StatutoryInstrument including attempts, evidence links, and audit timeline.
     *
     * @return array<string, mixed>
     */
    public function getInstrumentDetail(
        StatutoryInstrument $instrument,
        User $actor,
        CarbonInterface|string|null $referenceDate = null
    ): array {
        $this->authorizeInstrumentAccess($instrument, $actor);

        $instrument->load([
            'authority',
            'obligationInstance.definition',
            'obligationDefinition',
            'ruleVersion.source',
            'currentDocument',
            'assignedTo',
            'activeRenewalAttempt.replacementDocument',
            'activeRenewalAttempt.priorDocument',
            'activeRenewalAttempt.assignedTo',
            'renewalAttempts.priorDocument',
            'renewalAttempts.replacementDocument',
            'renewalAttempts.startedBy',
            'renewalAttempts.submittedBy',
            'renewalAttempts.decisionRecordedBy',
            'evidenceLinks.document.uploader',
            'evidenceLinks.document.verifiedBy',
            'evidenceLinks.supersededByDocument',
            'evidenceLinks.linkedBy',
            'activityLogs.user',
        ]);

        $membership = Membership::where('organisation_id', $instrument->organisation_id)
            ->where('user_id', $actor->id)
            ->where('status', 'ACTIVE')
            ->first();

        $isReviewerOrAdmin = $membership && in_array($membership->role, ['OWNER', 'ADMIN', 'COMPLIANCE_OFFICER'], true);

        $activeAttempt = $instrument->activeRenewalAttempt;
        $availableTransitions = $activeAttempt
            ? $this->stateMachine->getAvailableTransitions($activeAttempt, $isReviewerOrAdmin, $referenceDate)
            : [];

        $attempts = $instrument->renewalAttempts->map(fn (RenewalAttempt $att) => [
            'id' => $att->id,
            'attempt_number' => $att->attempt_number,
            'is_active_attempt' => $att->is_active_attempt,
            'workflow_status' => $att->workflow_status,
            'workflow_status_label' => RenewalLifecycleStateMachine::WORKFLOW_STATUSES[$att->workflow_status] ?? $att->workflow_status,
            'prior_reference_number' => $att->prior_reference_number,
            'prior_issue_date' => $att->prior_issue_date?->toDateString(),
            'prior_expiry_date' => $att->prior_expiry_date?->toDateString(),
            'prior_document' => $att->priorDocument ? [
                'id' => $att->priorDocument->id,
                'title' => $att->priorDocument->title,
                'file_name' => $att->priorDocument->file_name,
                'sha256_hash' => $att->priorDocument->sha256_hash,
                'verification_status' => $att->priorDocument->verification_status,
            ] : null,
            'replacement_reference_number' => $att->replacement_reference_number,
            'replacement_issue_date' => $att->replacement_issue_date?->toDateString(),
            'replacement_expiry_date' => $att->replacement_expiry_date?->toDateString(),
            'replacement_document' => $att->replacementDocument ? [
                'id' => $att->replacementDocument->id,
                'title' => $att->replacementDocument->title,
                'file_name' => $att->replacementDocument->file_name,
                'sha256_hash' => $att->replacementDocument->sha256_hash,
                'verification_status' => $att->replacementDocument->verification_status,
            ] : null,
            'submission_reference' => $att->submission_reference,
            'authority_receipt_number' => $att->authority_receipt_number,
            'submitted_at' => $att->submitted_at?->format('Y-m-d H:i'),
            'submitted_by' => $att->submittedBy?->name,
            'decision_outcome' => $att->decision_outcome,
            'decision_recorded_at' => $att->decision_recorded_at?->format('Y-m-d H:i'),
            'decision_recorded_by' => $att->decisionRecordedBy?->name,
            'rejection_reason' => $att->rejection_reason,
            'notes' => $att->notes,
            'lock_version' => $att->lock_version,
        ])->values();

        $evidenceLinks = $instrument->evidenceLinks->map(fn (RenewalEvidenceLink $link) => [
            'id' => $link->id,
            'renewal_attempt_id' => $link->renewal_attempt_id,
            'document_purpose' => $link->document_purpose,
            'document_purpose_label' => RenewalEvidenceLink::SUPPORTED_PURPOSES[$link->document_purpose] ?? $link->document_purpose,
            'is_superseded' => $link->is_superseded,
            'superseded_at' => $link->superseded_at?->format('Y-m-d H:i'),
            'superseded_by_document_name' => $link->supersededByDocument?->file_name,
            'notes' => $link->notes,
            'linked_by' => $link->linkedBy?->name,
            'created_at' => $link->created_at?->format('Y-m-d H:i'),
            'document' => $link->document ? [
                'id' => $link->document->id,
                'title' => $link->document->title,
                'file_name' => $link->document->file_name,
                'document_type' => $link->document->document_type,
                'mime_type' => $link->document->mime_type,
                'file_size' => $link->document->formattedFileSize(),
                'sha256_hash' => $link->document->sha256_hash,
                'verification_status' => $link->document->verification_status,
                'issue_date' => $link->document->issue_date?->toDateString(),
                'expiry_date' => $link->document->expiry_date?->toDateString(),
                'uploader_name' => $link->document->uploader?->name,
                'verified_by_name' => $link->document->verifiedBy?->name,
                'preview_url' => "/documents/{$link->document->id}/preview",
                'download_url' => "/documents/{$link->document->id}/download",
            ] : null,
        ])->values();

        $availableVaultDocs = ComplianceDocument::withoutGlobalScopes()
            ->where('tenant_id', $instrument->tenant_id)
            ->where('organisation_id', $instrument->organisation_id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (ComplianceDocument $doc) => [
                'id' => $doc->id,
                'title' => $doc->title,
                'file_name' => $doc->file_name,
                'document_type' => $doc->document_type,
                'verification_status' => $doc->verification_status,
                'issue_date' => $doc->issue_date?->toDateString(),
                'expiry_date' => $doc->expiry_date?->toDateString(),
            ])
            ->values();

        $timeline = $instrument->activityLogs->map(fn (RenewalActivityLog $log) => [
            'id' => $log->id,
            'activity_type' => $log->activity_type,
            'from_validity_status' => $log->from_validity_status,
            'to_validity_status' => $log->to_validity_status,
            'from_workflow_status' => $log->from_workflow_status,
            'to_workflow_status' => $log->to_workflow_status,
            'notes' => $log->notes,
            'metadata' => $log->metadata,
            'actor' => $log->user ? [
                'id' => $log->user->id,
                'name' => $log->user->name,
            ] : null,
            'created_at' => $log->created_at?->toIso8601String(),
        ])->values();

        return [
            'instrument' => $this->formatInstrumentItem($instrument, $referenceDate),
            'active_attempt' => $activeAttempt ? $attempts->firstWhere('id', $activeAttempt->id) : null,
            'available_transitions' => $availableTransitions,
            'attempts' => $attempts,
            'evidence_links' => $evidenceLinks,
            'available_vault_documents' => $availableVaultDocs,
            'supported_purposes' => RenewalEvidenceLink::SUPPORTED_PURPOSES,
            'timeline' => $timeline,
            'can_manage' => $isReviewerOrAdmin,
        ];
    }

    /**
     * Register a new or existing statutory instrument for an organisation.
     *
     * @param Organisation $organisation
     * @param array<string, mixed> $data
     * @param User $actor
     * @return StatutoryInstrument
     */
    public function registerInstrument(
        Organisation $organisation,
        array $data,
        User $actor
    ): StatutoryInstrument {
        $membership = Membership::where('organisation_id', $organisation->id)
            ->where('user_id', $actor->id)
            ->where('status', 'ACTIVE')
            ->first();

        if (!$membership || $membership->tenant_id !== $organisation->tenant_id) {
            throw new AuthorizationException('Unauthorized access: You do not have active membership in this organisation.');
        }

        $category = strtoupper((string) ($data['instrument_category'] ?? 'LICENCE'));
        if (!array_key_exists($category, self::SUPPORTED_CATEGORIES)) {
            throw new InvalidArgumentException("Unsupported instrument category [{$category}].");
        }

        // Resolve optional obligation instance and definition
        $obligationInstance = null;
        $obligationDefinition = null;
        if (!empty($data['obligation_instance_id'])) {
            $obligationInstance = ObligationInstance::withoutGlobalScopes()
                ->where('id', $data['obligation_instance_id'])
                ->where('tenant_id', $organisation->tenant_id)
                ->where('organisation_id', $organisation->id)
                ->with('definition.authority')
                ->first();

            if (!$obligationInstance) {
                throw new AuthorizationException('Selected obligation instance does not belong to the active organisation.');
            }
            $obligationDefinition = $obligationInstance->definition;
        } elseif (!empty($data['obligation_definition_id'])) {
            $obligationDefinition = ObligationDefinition::find($data['obligation_definition_id']);
        }

        // Resolve optional regulatory authority
        $authorityId = $data['regulatory_authority_id'] ?? $obligationDefinition?->authority_id ?? null;
        $authority = $authorityId ? RegulatoryAuthority::find($authorityId) : null;
        $authorityName = trim((string) ($data['issuing_authority_name'] ?? $authority?->name ?? 'Statutory Authority'));

        // Resolve optional approved RuleVersion
        $ruleVersion = null;
        if (!empty($data['rule_version_id'])) {
            $ruleVersion = RuleVersion::with('source')->find($data['rule_version_id']);
        } elseif ($obligationDefinition) {
            $ruleVersion = $this->resolveApprovedRuleVersionForDefinition($obligationDefinition, $data['issue_date'] ?? null);
        }

        // Validate optional current document from vault
        $currentDoc = null;
        if (!empty($data['current_document_id'])) {
            $currentDoc = ComplianceDocument::withoutGlobalScopes()
                ->where('id', $data['current_document_id'])
                ->where('tenant_id', $organisation->tenant_id)
                ->where('organisation_id', $organisation->id)
                ->first();

            if (!$currentDoc) {
                throw new AuthorizationException('Selected current document does not belong to this organisation.');
            }
        }

        // Validate optional assignee
        $assigneeId = null;
        if (!empty($data['assigned_to_user_id'])) {
            $assigneeMembership = Membership::where('organisation_id', $organisation->id)
                ->where('user_id', $data['assigned_to_user_id'])
                ->where('status', 'ACTIVE')
                ->first();
            if (!$assigneeMembership || $assigneeMembership->tenant_id !== $organisation->tenant_id) {
                throw new AuthorizationException('Assigned user must be an active member of this organisation.');
            }
            $assigneeId = $assigneeMembership->user_id;
        }

        $calc = $this->deadlineCalculator->calculate(
            instrumentDates: [
                'issue_date' => $data['issue_date'] ?? $currentDoc?->issue_date,
                'expiry_date' => $data['expiry_date'] ?? $currentDoc?->expiry_date,
                'trigger_event_date' => $data['trigger_event_date'] ?? null,
                'source_citation' => $data['source_citation'] ?? null,
            ],
            ruleVersion: $ruleVersion,
            definition: $obligationDefinition,
            referenceDate: $data['reference_date'] ?? null
        );

        return DB::transaction(function () use (
            $organisation,
            $data,
            $actor,
            $category,
            $obligationInstance,
            $obligationDefinition,
            $authority,
            $authorityName,
            $ruleVersion,
            $currentDoc,
            $assigneeId,
            $calc
        ) {
            $instrument = new StatutoryInstrument([
                'tenant_id' => $organisation->tenant_id,
                'organisation_id' => $organisation->id,
                'obligation_instance_id' => $obligationInstance?->id,
                'obligation_definition_id' => $obligationDefinition?->id,
                'regulatory_authority_id' => $authority?->id,
                'rule_version_id' => $ruleVersion?->id,
                'instrument_category' => $category,
                'title' => trim((string) $data['title']),
                'issuing_authority_name' => $authorityName,
                'reference_number' => !empty($data['reference_number']) ? trim((string) $data['reference_number']) : null,
                'premises_or_scope' => !empty($data['premises_or_scope']) ? trim((string) $data['premises_or_scope']) : null,
                'issue_date' => $calc['issue_date'],
                'expiry_date' => $calc['expiry_date'],
                'renewal_window_opens_at' => $calc['renewal_window_opens_at'],
                'statutory_renewal_deadline' => $calc['statutory_renewal_deadline'],
                'deadline_verification_status' => $calc['deadline_verification_status'],
                'deadline_calculation_basis' => $calc['deadline_calculation_basis'],
                'source_citation' => $calc['source_citation'],
                'current_document_id' => $currentDoc?->id,
                'assigned_to_user_id' => $assigneeId,
                'created_by_user_id' => $actor->id,
                'current_workflow_status' => RenewalLifecycleStateMachine::WORKFLOW_NONE,
                'notes' => $data['notes'] ?? null,
                'metadata_version' => 1,
                'lock_version' => 1,
            ]);

            $validityStatus = $this->stateMachine->evaluateInstrumentValidity($instrument, $data['reference_date'] ?? null);
            $instrument->validity_status = $validityStatus;
            $instrument->save();

            if ($currentDoc) {
                RenewalEvidenceLink::create([
                    'tenant_id' => $organisation->tenant_id,
                    'organisation_id' => $organisation->id,
                    'statutory_instrument_id' => $instrument->id,
                    'renewal_attempt_id' => null,
                    'compliance_document_id' => $currentDoc->id,
                    'document_purpose' => RenewalEvidenceLink::PURPOSE_CURRENT_INSTRUMENT,
                    'is_superseded' => false,
                    'linked_by_user_id' => $actor->id,
                    'notes' => 'Linked as initial current instrument upon registration.',
                ]);
            }

            RenewalActivityLog::create([
                'tenant_id' => $organisation->tenant_id,
                'organisation_id' => $organisation->id,
                'statutory_instrument_id' => $instrument->id,
                'renewal_attempt_id' => null,
                'user_id' => $actor->id,
                'activity_type' => 'INSTRUMENT_REGISTERED',
                'from_validity_status' => null,
                'to_validity_status' => $validityStatus,
                'from_workflow_status' => null,
                'to_workflow_status' => RenewalLifecycleStateMachine::WORKFLOW_NONE,
                'notes' => $data['notes'] ?? "Registered statutory instrument [{$instrument->title}].",
                'metadata' => [
                    'reference_number' => $instrument->reference_number,
                    'issue_date' => $instrument->issue_date?->toDateString(),
                    'expiry_date' => $instrument->expiry_date?->toDateString(),
                    'deadline_verification_status' => $instrument->deadline_verification_status,
                ],
                'created_at' => Carbon::now(),
            ]);

            AuditEvent::create([
                'tenant_id' => $organisation->tenant_id,
                'user_id' => $actor->id,
                'event_type' => 'STATUTORY_INSTRUMENT_REGISTERED',
                'auditable_type' => StatutoryInstrument::class,
                'auditable_id' => $instrument->id,
                'payload' => [
                    'title' => $instrument->title,
                    'category' => $instrument->instrument_category,
                    'reference_number' => $instrument->reference_number,
                    'expiry_date' => $instrument->expiry_date?->toDateString(),
                    'validity_status' => $validityStatus,
                ],
            ]);

            return $instrument->fresh(['authority', 'currentDocument', 'assignedTo']);
        });
    }

    /**
     * Perform a versioned, audited metadata correction on a StatutoryInstrument.
     *
     * @param StatutoryInstrument $instrument
     * @param array<string, mixed> $data
     * @param User $actor
     * @param int|null $lockVersion
     */
    public function updateInstrumentMetadata(
        StatutoryInstrument $instrument,
        array $data,
        User $actor,
        ?int $lockVersion = null
    ): StatutoryInstrument {
        $this->authorizeInstrumentAdmin($instrument, $actor);

        $reason = trim((string) ($data['correction_reason'] ?? $data['notes'] ?? ''));
        if ($reason === '') {
            throw new InvalidArgumentException('A mandatory correction reason is required to modify statutory instrument metadata.');
        }

        return DB::transaction(function () use ($instrument, $data, $actor, $lockVersion, $reason) {
            $locked = StatutoryInstrument::withoutGlobalScopes()
                ->where('id', $instrument->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockVersion !== null && $locked->lock_version !== $lockVersion) {
                throw new ConcurrentUpdateException(
                    'Conflict detected: This statutory instrument was modified by another user. Please refresh and try again.'
                );
            }

            $previousValues = [
                'title' => $locked->title,
                'reference_number' => $locked->reference_number,
                'issue_date' => $locked->issue_date?->toDateString(),
                'expiry_date' => $locked->expiry_date?->toDateString(),
                'premises_or_scope' => $locked->premises_or_scope,
                'metadata_version' => $locked->metadata_version,
            ];

            $newIssue = array_key_exists('issue_date', $data) ? $data['issue_date'] : $locked->issue_date;
            $newExpiry = array_key_exists('expiry_date', $data) ? $data['expiry_date'] : $locked->expiry_date;
            $ruleVersion = !empty($data['rule_version_id'])
                ? RuleVersion::with('source')->find($data['rule_version_id'])
                : $locked->ruleVersion;

            $calc = $this->deadlineCalculator->calculate(
                instrumentDates: [
                    'issue_date' => $newIssue,
                    'expiry_date' => $newExpiry,
                    'source_citation' => $data['source_citation'] ?? $locked->source_citation,
                ],
                ruleVersion: $ruleVersion,
                definition: $locked->obligationDefinition
            );

            $fromValidity = $locked->validity_status;

            $locked->fill([
                'title' => isset($data['title']) ? trim((string) $data['title']) : $locked->title,
                'reference_number' => array_key_exists('reference_number', $data) ? $data['reference_number'] : $locked->reference_number,
                'premises_or_scope' => array_key_exists('premises_or_scope', $data) ? $data['premises_or_scope'] : $locked->premises_or_scope,
                'issue_date' => $calc['issue_date'],
                'expiry_date' => $calc['expiry_date'],
                'renewal_window_opens_at' => $calc['renewal_window_opens_at'],
                'statutory_renewal_deadline' => $calc['statutory_renewal_deadline'],
                'deadline_verification_status' => $calc['deadline_verification_status'],
                'deadline_calculation_basis' => $calc['deadline_calculation_basis'],
                'source_citation' => $calc['source_citation'],
                'rule_version_id' => $ruleVersion?->id,
                'metadata_version' => $locked->metadata_version + 1,
                'lock_version' => $locked->lock_version + 1,
            ]);

            $toValidity = $this->stateMachine->evaluateInstrumentValidity($locked);
            $locked->validity_status = $toValidity;
            $locked->save();

            RenewalActivityLog::create([
                'tenant_id' => $locked->tenant_id,
                'organisation_id' => $locked->organisation_id,
                'statutory_instrument_id' => $locked->id,
                'renewal_attempt_id' => $locked->activeRenewalAttempt?->id,
                'user_id' => $actor->id,
                'activity_type' => 'METADATA_CORRECTED',
                'from_validity_status' => $fromValidity,
                'to_validity_status' => $toValidity,
                'from_workflow_status' => $locked->current_workflow_status,
                'to_workflow_status' => $locked->current_workflow_status,
                'notes' => $reason,
                'metadata' => [
                    'previous' => $previousValues,
                    'new' => [
                        'title' => $locked->title,
                        'reference_number' => $locked->reference_number,
                        'issue_date' => $locked->issue_date?->toDateString(),
                        'expiry_date' => $locked->expiry_date?->toDateString(),
                        'metadata_version' => $locked->metadata_version,
                    ],
                ],
                'created_at' => Carbon::now(),
            ]);

            AuditEvent::create([
                'tenant_id' => $locked->tenant_id,
                'user_id' => $actor->id,
                'event_type' => 'STATUTORY_INSTRUMENT_METADATA_CORRECTED',
                'auditable_type' => StatutoryInstrument::class,
                'auditable_id' => $locked->id,
                'payload' => [
                    'reason' => $reason,
                    'previous' => $previousValues,
                    'metadata_version' => $locked->metadata_version,
                ],
            ]);

            return $locked->fresh();
        });
    }

    /**
     * Assign or reassign a responsible owner to a StatutoryInstrument and its active renewal attempt.
     */
    public function assignOwner(
        StatutoryInstrument $instrument,
        ?User $assignee,
        User $actor,
        ?int $lockVersion = null,
        ?string $notes = null
    ): StatutoryInstrument {
        $this->authorizeInstrumentAdmin($instrument, $actor);

        if ($assignee !== null) {
            $targetMembership = Membership::where('organisation_id', $instrument->organisation_id)
                ->where('user_id', $assignee->id)
                ->where('status', 'ACTIVE')
                ->first();

            if (!$targetMembership || $targetMembership->tenant_id !== $instrument->tenant_id) {
                throw new AuthorizationException(
                    'Cannot assign renewal responsibility to a user outside the active organisation.'
                );
            }
        }

        return DB::transaction(function () use ($instrument, $assignee, $actor, $lockVersion, $notes) {
            $locked = StatutoryInstrument::withoutGlobalScopes()
                ->where('id', $instrument->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockVersion !== null && $locked->lock_version !== $lockVersion) {
                throw new ConcurrentUpdateException(
                    'Conflict detected: This statutory instrument was modified by another user. Please refresh and try again.'
                );
            }

            $fromUserId = $locked->assigned_to_user_id;

            $locked->update([
                'assigned_to_user_id' => $assignee?->id,
                'lock_version' => $locked->lock_version + 1,
            ]);

            $activeAttempt = RenewalAttempt::withoutGlobalScopes()
                ->where('statutory_instrument_id', $locked->id)
                ->where('is_active_attempt', true)
                ->first();

            if ($activeAttempt) {
                $activeAttempt->update([
                    'assigned_to_user_id' => $assignee?->id,
                ]);
            }

            RenewalActivityLog::create([
                'tenant_id' => $locked->tenant_id,
                'organisation_id' => $locked->organisation_id,
                'statutory_instrument_id' => $locked->id,
                'renewal_attempt_id' => $activeAttempt?->id,
                'user_id' => $actor->id,
                'activity_type' => 'OWNER_ASSIGNED',
                'from_validity_status' => $locked->validity_status,
                'to_validity_status' => $locked->validity_status,
                'from_workflow_status' => $locked->current_workflow_status,
                'to_workflow_status' => $locked->current_workflow_status,
                'notes' => $notes ?? ($assignee ? "Assigned renewal responsibility to {$assignee->name}." : 'Renewal responsibility unassigned.'),
                'metadata' => [
                    'from_user_id' => $fromUserId,
                    'to_user_id' => $assignee?->id,
                    'to_user_name' => $assignee?->name,
                ],
                'created_at' => Carbon::now(),
            ]);

            AuditEvent::create([
                'tenant_id' => $locked->tenant_id,
                'user_id' => $actor->id,
                'event_type' => 'STATUTORY_INSTRUMENT_OWNER_ASSIGNED',
                'auditable_type' => StatutoryInstrument::class,
                'auditable_id' => $locked->id,
                'payload' => [
                    'from_user_id' => $fromUserId,
                    'to_user_id' => $assignee?->id,
                    'notes' => $notes,
                ],
            ]);

            return $locked->fresh(['assignedTo']);
        });
    }

    /**
     * Start a new RenewalAttempt for a StatutoryInstrument.
     *
     * Prevents simultaneous duplicate active renewal attempts via pessimistic locking AND unique DB constraint.
     *
     * @param StatutoryInstrument $instrument
     * @param User $actor
     * @param array<string, mixed> $data
     * @param int|null $lockVersion
     */
    public function startRenewalAttempt(
        StatutoryInstrument $instrument,
        User $actor,
        array $data = [],
        ?int $lockVersion = null
    ): RenewalAttempt {
        $this->authorizeInstrumentAccess($instrument, $actor);

        if ($instrument->validity_status === RenewalLifecycleStateMachine::VALIDITY_CANCELLED) {
            throw new InvalidLifecycleTransitionException(
                'Cannot start a renewal application for a permanently CANCELLED statutory instrument.'
            );
        }

        return DB::transaction(function () use ($instrument, $actor, $data, $lockVersion) {
            $locked = StatutoryInstrument::withoutGlobalScopes()
                ->where('id', $instrument->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockVersion !== null && $locked->lock_version !== $lockVersion) {
                throw new ConcurrentUpdateException(
                    'Conflict detected: This statutory instrument has been modified by another user.'
                );
            }

            $existingActive = RenewalAttempt::withoutGlobalScopes()
                ->where('statutory_instrument_id', $locked->id)
                ->where('is_active_attempt', true)
                ->first();

            if ($existingActive) {
                throw new InvalidLifecycleTransitionException(
                    "An active renewal attempt (#{$existingActive->attempt_number} in status [{$existingActive->workflow_status}]) already exists for this instrument. Complete, reject, or withdraw the active attempt before starting a new one."
                );
            }

            $maxAttemptNum = (int) RenewalAttempt::withoutGlobalScopes()
                ->where('statutory_instrument_id', $locked->id)
                ->max('attempt_number');

            $nextAttemptNumber = $maxAttemptNum + 1;
            $initialStatus = strtoupper((string) ($data['initial_status'] ?? RenewalLifecycleStateMachine::WORKFLOW_RENEWAL_IN_PROGRESS));
            if (!in_array($initialStatus, [
                RenewalLifecycleStateMachine::WORKFLOW_RENEWAL_UPCOMING,
                RenewalLifecycleStateMachine::WORKFLOW_RENEWAL_IN_PROGRESS,
                RenewalLifecycleStateMachine::WORKFLOW_AWAITING_DOCUMENTS,
            ], true)) {
                $initialStatus = RenewalLifecycleStateMachine::WORKFLOW_RENEWAL_IN_PROGRESS;
            }

            $attempt = RenewalAttempt::create([
                'tenant_id' => $locked->tenant_id,
                'organisation_id' => $locked->organisation_id,
                'statutory_instrument_id' => $locked->id,
                'obligation_instance_id' => $locked->obligation_instance_id,
                'rule_version_id' => $locked->rule_version_id,
                'attempt_number' => $nextAttemptNumber,
                'is_active_attempt' => true,
                'active_attempt_guard' => 'ACTIVE',
                'workflow_status' => $initialStatus,
                'prior_reference_number' => $locked->reference_number,
                'prior_issue_date' => $locked->issue_date,
                'prior_expiry_date' => $locked->expiry_date,
                'prior_document_id' => $locked->current_document_id,
                'statutory_renewal_deadline' => $locked->statutory_renewal_deadline,
                'deadline_verification_status' => $locked->deadline_verification_status,
                'deadline_calculation_basis' => $locked->deadline_calculation_basis,
                'assigned_to_user_id' => $locked->assigned_to_user_id ?? $actor->id,
                'started_by_user_id' => $actor->id,
                'started_at' => Carbon::now(),
                'notes' => $data['notes'] ?? "Started renewal attempt #{$nextAttemptNumber}.",
                'lock_version' => 1,
            ]);

            $fromWorkflow = $locked->current_workflow_status;
            // Note: Starting a renewal attempt NEVER changes the underlying instrument validity_status
            $locked->update([
                'current_workflow_status' => $initialStatus,
                'lock_version' => $locked->lock_version + 1,
            ]);

            RenewalActivityLog::create([
                'tenant_id' => $locked->tenant_id,
                'organisation_id' => $locked->organisation_id,
                'statutory_instrument_id' => $locked->id,
                'renewal_attempt_id' => $attempt->id,
                'user_id' => $actor->id,
                'activity_type' => 'RENEWAL_ATTEMPT_STARTED',
                'from_validity_status' => $locked->validity_status,
                'to_validity_status' => $locked->validity_status,
                'from_workflow_status' => $fromWorkflow,
                'to_workflow_status' => $initialStatus,
                'notes' => $data['notes'] ?? "Started renewal application attempt #{$nextAttemptNumber}.",
                'metadata' => [
                    'attempt_number' => $nextAttemptNumber,
                    'prior_reference_number' => $locked->reference_number,
                    'prior_expiry_date' => $locked->expiry_date?->toDateString(),
                ],
                'created_at' => Carbon::now(),
            ]);

            AuditEvent::create([
                'tenant_id' => $locked->tenant_id,
                'user_id' => $actor->id,
                'event_type' => 'RENEWAL_ATTEMPT_STARTED',
                'auditable_type' => RenewalAttempt::class,
                'auditable_id' => $attempt->id,
                'payload' => [
                    'statutory_instrument_id' => $locked->id,
                    'attempt_number' => $nextAttemptNumber,
                    'workflow_status' => $initialStatus,
                ],
            ]);

            return $attempt->fresh(['instrument']);
        });
    }

    /**
     * Transition a RenewalAttempt through the controlled renewal state machine.
     *
     * CRITICAL GOVERNANCE SAFEGUARDS:
     * - DECLARED_SUBMITTED / AWAITING_AUTHORITY_DECISION never extends an expired instrument's validity.
     * - RENEWED requires reviewer/admin authorization AND a human-VERIFIED replacement certificate.
     * - When RENEWED is confirmed, the prior certificate is preserved as PRIOR_INSTRUMENT (is_superseded = true).
     *
     * @param RenewalAttempt $attempt
     * @param string $targetStatus
     * @param User $actor
     * @param array<string, mixed> $data
     * @param int|null $lockVersion
     */
    public function transitionRenewalAttempt(
        RenewalAttempt $attempt,
        string $targetStatus,
        User $actor,
        array $data = [],
        ?int $lockVersion = null
    ): RenewalAttempt {
        $instrument = StatutoryInstrument::withoutGlobalScopes()
            ->where('id', $attempt->statutory_instrument_id)
            ->firstOrFail();

        $this->authorizeInstrumentAccess($instrument, $actor);

        $membership = Membership::where('organisation_id', $instrument->organisation_id)
            ->where('user_id', $actor->id)
            ->where('status', 'ACTIVE')
            ->first();

        $isReviewerOrAdmin = $membership && in_array($membership->role, ['OWNER', 'ADMIN', 'COMPLIANCE_OFFICER'], true);
        $normalizedTarget = strtoupper(trim($targetStatus));

        return DB::transaction(function () use (
            $attempt,
            $instrument,
            $normalizedTarget,
            $actor,
            $isReviewerOrAdmin,
            $data,
            $lockVersion
        ) {
            $lockedAttempt = RenewalAttempt::withoutGlobalScopes()
                ->where('id', $attempt->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedInstrument = StatutoryInstrument::withoutGlobalScopes()
                ->where('id', $instrument->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockVersion !== null && $lockedAttempt->lock_version !== $lockVersion) {
                throw new ConcurrentUpdateException(
                    'Conflict detected: This renewal application has been modified by another user. Please refresh and try again.'
                );
            }

            $this->stateMachine->validateTransition(
                attempt: $lockedAttempt,
                targetStatus: $normalizedTarget,
                isReviewerOrAdmin: $isReviewerOrAdmin,
                context: $data
            );

            $fromWorkflow = $lockedAttempt->workflow_status;
            $fromValidity = $lockedInstrument->validity_status;
            $toValidity = $this->stateMachine->evaluateInstrumentValidity($lockedInstrument, $data['reference_date'] ?? null);

            $attemptUpdates = [
                'workflow_status' => $normalizedTarget,
                'lock_version' => $lockedAttempt->lock_version + 1,
            ];

            if (!empty($data['notes'])) {
                $attemptUpdates['notes'] = trim((string) $data['notes']);
            }
            if (!empty($data['submission_reference'])) {
                $attemptUpdates['submission_reference'] = trim((string) $data['submission_reference']);
            }
            if (!empty($data['authority_receipt_number'])) {
                $attemptUpdates['authority_receipt_number'] = trim((string) $data['authority_receipt_number']);
            }

            $isTerminal = $this->stateMachine->isTerminalAttemptStatus($normalizedTarget);
            if ($isTerminal) {
                $attemptUpdates['is_active_attempt'] = false;
                $attemptUpdates['active_attempt_guard'] = null;
            }

            if ($normalizedTarget === RenewalLifecycleStateMachine::WORKFLOW_DECLARED_SUBMITTED) {
                $attemptUpdates['submitted_by_user_id'] = $actor->id;
                $attemptUpdates['submitted_at'] = Carbon::now();
            } elseif ($normalizedTarget === RenewalLifecycleStateMachine::WORKFLOW_REJECTED) {
                $rejectionReason = trim((string) ($data['rejection_reason'] ?? $data['notes'] ?? ''));
                $attemptUpdates['decision_outcome'] = 'REJECTED';
                $attemptUpdates['decision_recorded_by_user_id'] = $actor->id;
                $attemptUpdates['decision_recorded_at'] = Carbon::now();
                $attemptUpdates['rejection_reason'] = $rejectionReason;
            } elseif (in_array($normalizedTarget, [
                RenewalLifecycleStateMachine::WORKFLOW_WITHDRAWN,
                RenewalLifecycleStateMachine::WORKFLOW_ABANDONED,
                RenewalLifecycleStateMachine::WORKFLOW_CANCELLED,
            ], true)) {
                $attemptUpdates['decision_outcome'] = $normalizedTarget;
                $attemptUpdates['decision_recorded_by_user_id'] = $actor->id;
                $attemptUpdates['decision_recorded_at'] = Carbon::now();
            } elseif ($normalizedTarget === RenewalLifecycleStateMachine::WORKFLOW_RENEWED) {
                // Find the verified replacement certificate linked to this attempt/instrument
                $replacementLink = RenewalEvidenceLink::withoutGlobalScopes()
                    ->where('tenant_id', $lockedAttempt->tenant_id)
                    ->where('organisation_id', $lockedAttempt->organisation_id)
                    ->where('statutory_instrument_id', $lockedAttempt->statutory_instrument_id)
                    ->where(function ($q) use ($lockedAttempt) {
                        $q->where('renewal_attempt_id', $lockedAttempt->id)
                          ->orWhereNull('renewal_attempt_id');
                    })
                    ->where('document_purpose', RenewalEvidenceLink::PURPOSE_REPLACEMENT_INSTRUMENT)
                    ->where('is_superseded', false)
                    ->whereHas('document', fn ($q) => $q->where('verification_status', 'VERIFIED'))
                    ->with('document')
                    ->latest('created_at')
                    ->firstOrFail();

                $replacementDoc = $replacementLink->document;

                $newRefNumber = !empty($data['replacement_reference_number'])
                    ? trim((string) $data['replacement_reference_number'])
                    : ($replacementDoc->extracted_metadata['registration_number'] ?? $lockedInstrument->reference_number);

                $newIssueDate = !empty($data['replacement_issue_date'])
                    ? Carbon::parse($data['replacement_issue_date'], RenewalDeadlineCalculator::TIMEZONE)->toDateString()
                    : ($replacementDoc->issue_date?->toDateString() ?? Carbon::now(RenewalDeadlineCalculator::TIMEZONE)->toDateString());

                $newExpiryDate = !empty($data['replacement_expiry_date'])
                    ? Carbon::parse($data['replacement_expiry_date'], RenewalDeadlineCalculator::TIMEZONE)->toDateString()
                    : $replacementDoc->expiry_date?->toDateString();

                // Synchronize replacement document dates if provided during renewal approval
                if ($newExpiryDate && $replacementDoc->expiry_date?->toDateString() !== $newExpiryDate) {
                    $replacementDoc->update([
                        'issue_date' => $newIssueDate,
                        'expiry_date' => $newExpiryDate,
                    ]);
                }

                // 1. Preserve & Supersede Prior Instrument Certificate(s) without deleting history or vault records
                $priorLinks = RenewalEvidenceLink::withoutGlobalScopes()
                    ->where('statutory_instrument_id', $lockedInstrument->id)
                    ->where('document_purpose', RenewalEvidenceLink::PURPOSE_CURRENT_INSTRUMENT)
                    ->where('is_superseded', false)
                    ->get();

                foreach ($priorLinks as $priorLink) {
                    $priorLink->update([
                        'document_purpose' => RenewalEvidenceLink::PURPOSE_PRIOR_INSTRUMENT,
                        'is_superseded' => true,
                        'superseded_at' => Carbon::now(),
                        'superseded_by_document_id' => $replacementDoc->id,
                    ]);

                    if ($priorLink->compliance_document_id && $priorLink->compliance_document_id !== $replacementDoc->id) {
                        $oldDoc = ComplianceDocument::withoutGlobalScopes()->find($priorLink->compliance_document_id);
                        if ($oldDoc && $oldDoc->verification_status === 'VERIFIED') {
                            $oldDoc->update([
                                'verification_status' => 'SUPERSEDED',
                            ]);
                        }
                    }
                }

                // Also create a CURRENT_INSTRUMENT link for the new active replacement certificate
                RenewalEvidenceLink::create([
                    'tenant_id' => $lockedInstrument->tenant_id,
                    'organisation_id' => $lockedInstrument->organisation_id,
                    'statutory_instrument_id' => $lockedInstrument->id,
                    'renewal_attempt_id' => $lockedAttempt->id,
                    'compliance_document_id' => $replacementDoc->id,
                    'document_purpose' => RenewalEvidenceLink::PURPOSE_CURRENT_INSTRUMENT,
                    'is_superseded' => false,
                    'linked_by_user_id' => $actor->id,
                    'notes' => "Promoted verified replacement certificate from renewal attempt #{$lockedAttempt->attempt_number}.",
                ]);

                // 2. Recalculate new cycle dates using RenewalDeadlineCalculator
                $newCycleCalc = $this->deadlineCalculator->calculate(
                    instrumentDates: [
                        'issue_date' => $newIssueDate,
                        'expiry_date' => $newExpiryDate,
                        'source_citation' => $lockedInstrument->source_citation,
                    ],
                    ruleVersion: $lockedInstrument->ruleVersion,
                    definition: $lockedInstrument->obligationDefinition,
                    referenceDate: $data['reference_date'] ?? null
                );

                $attemptUpdates['decision_outcome'] = 'APPROVED_RENEWED';
                $attemptUpdates['decision_recorded_by_user_id'] = $actor->id;
                $attemptUpdates['decision_recorded_at'] = Carbon::now();
                $attemptUpdates['replacement_document_id'] = $replacementDoc->id;
                $attemptUpdates['replacement_reference_number'] = $newRefNumber;
                $attemptUpdates['replacement_issue_date'] = $newCycleCalc['issue_date'];
                $attemptUpdates['replacement_expiry_date'] = $newCycleCalc['expiry_date'];

                $lockedInstrument->fill([
                    'reference_number' => $newRefNumber,
                    'issue_date' => $newCycleCalc['issue_date'],
                    'expiry_date' => $newCycleCalc['expiry_date'],
                    'renewal_window_opens_at' => $newCycleCalc['renewal_window_opens_at'],
                    'statutory_renewal_deadline' => $newCycleCalc['statutory_renewal_deadline'],
                    'deadline_verification_status' => $newCycleCalc['deadline_verification_status'],
                    'deadline_calculation_basis' => $newCycleCalc['deadline_calculation_basis'],
                    'current_document_id' => $replacementDoc->id,
                    'metadata_version' => $lockedInstrument->metadata_version + 1,
                ]);

                $toValidity = $this->stateMachine->evaluateInstrumentValidity($lockedInstrument, $data['reference_date'] ?? null);

                // Recalculate parent ObligationInstance evidence status if linked
                if ($lockedInstrument->obligation_instance_id) {
                    $obInstance = ObligationInstance::withoutGlobalScopes()->find($lockedInstrument->obligation_instance_id);
                    if ($obInstance) {
                        if ($replacementDoc->obligation_instance_id !== $obInstance->id) {
                            $replacementDoc->update(['obligation_instance_id' => $obInstance->id]);
                        }
                        $newEvStatus = $this->evidenceStatusEvaluator->evaluate($obInstance);
                        $obInstance->update(['evidence_status' => $newEvStatus]);
                    }
                }
            }

            $lockedAttempt->update($attemptUpdates);

            $lockedInstrument->update([
                'validity_status' => $toValidity,
                'current_workflow_status' => $normalizedTarget,
                'lock_version' => $lockedInstrument->lock_version + 1,
            ]);

            $activityType = match ($normalizedTarget) {
                RenewalLifecycleStateMachine::WORKFLOW_RENEWED => 'RENEWAL_COMPLETED',
                RenewalLifecycleStateMachine::WORKFLOW_REJECTED => 'RENEWAL_REJECTED',
                default => 'WORKFLOW_TRANSITION',
            };

            RenewalActivityLog::create([
                'tenant_id' => $lockedInstrument->tenant_id,
                'organisation_id' => $lockedInstrument->organisation_id,
                'statutory_instrument_id' => $lockedInstrument->id,
                'renewal_attempt_id' => $lockedAttempt->id,
                'user_id' => $actor->id,
                'activity_type' => $activityType,
                'from_validity_status' => $fromValidity,
                'to_validity_status' => $toValidity,
                'from_workflow_status' => $fromWorkflow,
                'to_workflow_status' => $normalizedTarget,
                'notes' => $data['notes'] ?? $data['rejection_reason'] ?? "Transitioned renewal attempt #{$lockedAttempt->attempt_number} from [{$fromWorkflow}] to [{$normalizedTarget}].",
                'metadata' => [
                    'attempt_number' => $lockedAttempt->attempt_number,
                    'submission_reference' => $lockedAttempt->submission_reference,
                    'authority_receipt_number' => $lockedAttempt->authority_receipt_number,
                    'replacement_document_id' => $lockedAttempt->replacement_document_id,
                    'replacement_expiry_date' => $lockedAttempt->replacement_expiry_date?->toDateString(),
                ],
                'created_at' => Carbon::now(),
            ]);

            AuditEvent::create([
                'tenant_id' => $lockedInstrument->tenant_id,
                'user_id' => $actor->id,
                'event_type' => 'RENEWAL_ATTEMPT_TRANSITIONED',
                'auditable_type' => RenewalAttempt::class,
                'auditable_id' => $lockedAttempt->id,
                'payload' => [
                    'statutory_instrument_id' => $lockedInstrument->id,
                    'attempt_number' => $lockedAttempt->attempt_number,
                    'from_workflow_status' => $fromWorkflow,
                    'to_workflow_status' => $normalizedTarget,
                    'from_validity_status' => $fromValidity,
                    'to_validity_status' => $toValidity,
                ],
            ]);

            return $lockedAttempt->fresh(['instrument', 'replacementDocument', 'priorDocument']);
        });
    }

    /**
     * Link a Document Vault document to a StatutoryInstrument (and optional RenewalAttempt) with explicit purpose.
     *
     * CRITICAL SAFEGUARD:
     * Linking or uploading a REPLACEMENT_INSTRUMENT document NEVER automatically transitions the instrument or attempt to RENEWED.
     */
    public function linkEvidence(
        StatutoryInstrument $instrument,
        ComplianceDocument $document,
        string $purpose,
        User $actor,
        ?RenewalAttempt $attempt = null,
        ?string $notes = null
    ): RenewalEvidenceLink {
        $this->authorizeInstrumentAccess($instrument, $actor);

        if ($document->tenant_id !== $instrument->tenant_id || $document->organisation_id !== $instrument->organisation_id) {
            throw new AuthorizationException(
                'Document and statutory instrument must belong to the same tenant and organisation.'
            );
        }

        $normalizedPurpose = strtoupper(trim($purpose));
        if (!array_key_exists($normalizedPurpose, RenewalEvidenceLink::SUPPORTED_PURPOSES)) {
            throw new InvalidArgumentException("Unsupported renewal evidence purpose [{$normalizedPurpose}].");
        }

        $targetAttempt = $attempt ?? $instrument->activeRenewalAttempt;
        if ($targetAttempt && $targetAttempt->statutory_instrument_id !== $instrument->id) {
            throw new InvalidArgumentException('Specified renewal attempt does not belong to this statutory instrument.');
        }

        return DB::transaction(function () use (
            $instrument,
            $document,
            $normalizedPurpose,
            $actor,
            $targetAttempt,
            $notes
        ) {
            $link = RenewalEvidenceLink::firstOrCreate(
                [
                    'tenant_id' => $instrument->tenant_id,
                    'organisation_id' => $instrument->organisation_id,
                    'statutory_instrument_id' => $instrument->id,
                    'renewal_attempt_id' => $targetAttempt?->id,
                    'compliance_document_id' => $document->id,
                    'document_purpose' => $normalizedPurpose,
                ],
                [
                    'is_superseded' => false,
                    'linked_by_user_id' => $actor->id,
                    'notes' => $notes,
                ]
            );

            if ($normalizedPurpose === RenewalEvidenceLink::PURPOSE_CURRENT_INSTRUMENT && $instrument->current_document_id === null) {
                $instrument->update(['current_document_id' => $document->id]);
            }

            if ($normalizedPurpose === RenewalEvidenceLink::PURPOSE_REPLACEMENT_INSTRUMENT && $targetAttempt !== null) {
                // Record replacement_document_id on the attempt, but NEVER automatically mark RENEWED!
                $targetAttempt->update([
                    'replacement_document_id' => $document->id,
                ]);
            }

            RenewalActivityLog::create([
                'tenant_id' => $instrument->tenant_id,
                'organisation_id' => $instrument->organisation_id,
                'statutory_instrument_id' => $instrument->id,
                'renewal_attempt_id' => $targetAttempt?->id,
                'user_id' => $actor->id,
                'activity_type' => 'EVIDENCE_LINKED',
                'from_validity_status' => $instrument->validity_status,
                'to_validity_status' => $instrument->validity_status,
                'from_workflow_status' => $instrument->current_workflow_status,
                'to_workflow_status' => $instrument->current_workflow_status,
                'notes' => $notes ?? "Linked vault document [{$document->file_name}] as [{$normalizedPurpose}].",
                'metadata' => [
                    'document_id' => $document->id,
                    'file_name' => $document->file_name,
                    'sha256_hash' => $document->sha256_hash,
                    'verification_status' => $document->verification_status,
                    'document_purpose' => $normalizedPurpose,
                ],
                'created_at' => Carbon::now(),
            ]);

            AuditEvent::create([
                'tenant_id' => $instrument->tenant_id,
                'user_id' => $actor->id,
                'event_type' => 'RENEWAL_EVIDENCE_LINKED',
                'auditable_type' => StatutoryInstrument::class,
                'auditable_id' => $instrument->id,
                'payload' => [
                    'document_id' => $document->id,
                    'document_purpose' => $normalizedPurpose,
                    'renewal_attempt_id' => $targetAttempt?->id,
                ],
            ]);

            return $link->fresh(['document']);
        });
    }

    /**
     * Unlink a RenewalEvidenceLink from an instrument/attempt (unless it is a historical superseded record).
     */
    public function unlinkEvidence(
        StatutoryInstrument $instrument,
        RenewalEvidenceLink $link,
        User $actor
    ): void {
        $this->authorizeInstrumentAccess($instrument, $actor);

        if ($link->statutory_instrument_id !== $instrument->id) {
            throw new InvalidArgumentException('Evidence link does not belong to this statutory instrument.');
        }

        if ($link->is_superseded || $link->document_purpose === RenewalEvidenceLink::PURPOSE_PRIOR_INSTRUMENT) {
            throw new InvalidArgumentException('Cannot unlink or delete historical superseded certificate records.');
        }

        DB::transaction(function () use ($instrument, $link, $actor) {
            $docName = $link->document?->file_name ?? $link->compliance_document_id;
            $purpose = $link->document_purpose;

            if ($instrument->current_document_id === $link->compliance_document_id && $purpose === RenewalEvidenceLink::PURPOSE_CURRENT_INSTRUMENT) {
                $instrument->update(['current_document_id' => null]);
            }

            $link->delete();

            RenewalActivityLog::create([
                'tenant_id' => $instrument->tenant_id,
                'organisation_id' => $instrument->organisation_id,
                'statutory_instrument_id' => $instrument->id,
                'renewal_attempt_id' => $link->renewal_attempt_id,
                'user_id' => $actor->id,
                'activity_type' => 'EVIDENCE_UNLINKED',
                'from_validity_status' => $instrument->validity_status,
                'to_validity_status' => $instrument->validity_status,
                'from_workflow_status' => $instrument->current_workflow_status,
                'to_workflow_status' => $instrument->current_workflow_status,
                'notes' => "Unlinked document [{$docName}] ({$purpose}).",
                'metadata' => [
                    'document_id' => $link->compliance_document_id,
                    'document_purpose' => $purpose,
                ],
                'created_at' => Carbon::now(),
            ]);
        });
    }

    /**
     * Record a suspension, cancellation, or restoration of an instrument's legal validity status.
     * Preserves all historical renewal attempts, documents, and audit logs.
     */
    public function changeInstrumentValidityStatus(
        StatutoryInstrument $instrument,
        string $targetValidity,
        User $actor,
        string $reason,
        ?int $lockVersion = null
    ): StatutoryInstrument {
        $this->authorizeInstrumentAdmin($instrument, $actor);

        $normalizedValidity = strtoupper(trim($targetValidity));
        if (!in_array($normalizedValidity, [
            RenewalLifecycleStateMachine::VALIDITY_SUSPENDED,
            RenewalLifecycleStateMachine::VALIDITY_CANCELLED,
            RenewalLifecycleStateMachine::VALIDITY_ACTIVE,
        ], true)) {
            throw new InvalidArgumentException("Unsupported administrative validity status override [{$normalizedValidity}].");
        }

        if (trim($reason) === '') {
            throw new InvalidArgumentException('A mandatory reason/legal basis is required to suspend, cancel, or restore a statutory instrument.');
        }

        return DB::transaction(function () use ($instrument, $normalizedValidity, $actor, $reason, $lockVersion) {
            $locked = StatutoryInstrument::withoutGlobalScopes()
                ->where('id', $instrument->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockVersion !== null && $locked->lock_version !== $lockVersion) {
                throw new ConcurrentUpdateException('Concurrent modification detected.');
            }

            $fromValidity = $locked->validity_status;
            $fromWorkflow = $locked->current_workflow_status;

            // If restoring to ACTIVE, re-evaluate against expiry date so an expired licence isn't falsely marked ACTIVE
            $resolvedValidity = $normalizedValidity;
            if ($normalizedValidity === RenewalLifecycleStateMachine::VALIDITY_ACTIVE) {
                $locked->validity_status = RenewalLifecycleStateMachine::VALIDITY_ACTIVE;
                $resolvedValidity = $this->stateMachine->evaluateInstrumentValidity($locked);
            }

            $locked->update([
                'validity_status' => $resolvedValidity,
                'suspension_or_cancellation_reason' => in_array($resolvedValidity, ['SUSPENDED', 'CANCELLED'], true) ? trim($reason) : null,
                'lock_version' => $locked->lock_version + 1,
            ]);

            // If cancelled, close any active renewal attempt without deleting history
            if ($resolvedValidity === RenewalLifecycleStateMachine::VALIDITY_CANCELLED) {
                $activeAttempt = RenewalAttempt::withoutGlobalScopes()
                    ->where('statutory_instrument_id', $locked->id)
                    ->where('is_active_attempt', true)
                    ->first();

                if ($activeAttempt) {
                    $activeAttempt->update([
                        'is_active_attempt' => false,
                        'active_attempt_guard' => null,
                        'workflow_status' => RenewalLifecycleStateMachine::WORKFLOW_CANCELLED,
                        'decision_outcome' => 'CANCELLED',
                        'decision_recorded_by_user_id' => $actor->id,
                        'decision_recorded_at' => Carbon::now(),
                        'notes' => "Closed due to instrument cancellation: {$reason}",
                    ]);
                    $locked->update(['current_workflow_status' => RenewalLifecycleStateMachine::WORKFLOW_CANCELLED]);
                }
            }

            $activityType = match ($resolvedValidity) {
                RenewalLifecycleStateMachine::VALIDITY_SUSPENDED => 'INSTRUMENT_SUSPENDED',
                RenewalLifecycleStateMachine::VALIDITY_CANCELLED => 'INSTRUMENT_CANCELLED',
                default => 'VALIDITY_STATUS_CHANGED',
            };

            RenewalActivityLog::create([
                'tenant_id' => $locked->tenant_id,
                'organisation_id' => $locked->organisation_id,
                'statutory_instrument_id' => $locked->id,
                'renewal_attempt_id' => $locked->activeRenewalAttempt?->id,
                'user_id' => $actor->id,
                'activity_type' => $activityType,
                'from_validity_status' => $fromValidity,
                'to_validity_status' => $resolvedValidity,
                'from_workflow_status' => $fromWorkflow,
                'to_workflow_status' => $locked->current_workflow_status,
                'notes' => trim($reason),
                'metadata' => [
                    'reason' => trim($reason),
                ],
                'created_at' => Carbon::now(),
            ]);

            AuditEvent::create([
                'tenant_id' => $locked->tenant_id,
                'user_id' => $actor->id,
                'event_type' => "STATUTORY_INSTRUMENT_{$resolvedValidity}",
                'auditable_type' => StatutoryInstrument::class,
                'auditable_id' => $locked->id,
                'payload' => [
                    'from_validity_status' => $fromValidity,
                    'to_validity_status' => $resolvedValidity,
                    'reason' => trim($reason),
                ],
            ]);

            return $locked->fresh();
        });
    }

    /**
     * Evaluate and transition expired statutory instruments and recalculate linked obligation evidence.
     *
     * CRITICAL SAFEGUARD:
     * Even if an instrument has a pending renewal attempt (e.g., DECLARED_SUBMITTED or AWAITING_AUTHORITY_DECISION),
     * once expiry_date < $asOfDate, the underlying instrument's validity_status MUST transition to EXPIRED.
     */
    public function recalculateExpirationsForOrganisation(
        ?Organisation $organisation = null,
        CarbonInterface|string|null $asOfDate = null
    ): int {
        $evalDate = $asOfDate
            ? Carbon::parse($asOfDate, RenewalDeadlineCalculator::TIMEZONE)->startOfDay()
            : Carbon::now(RenewalDeadlineCalculator::TIMEZONE)->startOfDay();
        $dateString = $evalDate->toDateString();

        $query = StatutoryInstrument::withoutGlobalScopes()
            ->whereNotIn('validity_status', [
                RenewalLifecycleStateMachine::VALIDITY_EXPIRED,
                RenewalLifecycleStateMachine::VALIDITY_SUSPENDED,
                RenewalLifecycleStateMachine::VALIDITY_CANCELLED,
                RenewalLifecycleStateMachine::VALIDITY_SUPERSEDED,
            ])
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<', $dateString);

        if ($organisation) {
            $query->where('tenant_id', $organisation->tenant_id)
                  ->where('organisation_id', $organisation->id);
        }

        $expiredInstruments = $query->get();
        $count = 0;

        foreach ($expiredInstruments as $inst) {
            DB::transaction(function () use ($inst, $evalDate, &$count) {
                $fromValidity = $inst->validity_status;
                $inst->update([
                    'validity_status' => RenewalLifecycleStateMachine::VALIDITY_EXPIRED,
                ]);

                if ($inst->current_document_id) {
                    $doc = ComplianceDocument::withoutGlobalScopes()->find($inst->current_document_id);
                    if ($doc && $doc->verification_status === 'VERIFIED' && $doc->expiry_date && $doc->expiry_date->lt($evalDate)) {
                        $doc->update(['verification_status' => 'EXPIRED']);
                    }
                }

                if ($inst->obligation_instance_id) {
                    $obligation = ObligationInstance::withoutGlobalScopes()->find($inst->obligation_instance_id);
                    if ($obligation) {
                        $newEvStatus = $this->evidenceStatusEvaluator->evaluate($obligation, $evalDate);
                        $obligation->update(['evidence_status' => $newEvStatus]);
                    }
                }

                RenewalActivityLog::create([
                    'tenant_id' => $inst->tenant_id,
                    'organisation_id' => $inst->organisation_id,
                    'statutory_instrument_id' => $inst->id,
                    'renewal_attempt_id' => $inst->activeRenewalAttempt?->id,
                    'user_id' => null,
                    'activity_type' => 'EXPIRY_RECALCULATED',
                    'from_validity_status' => $fromValidity,
                    'to_validity_status' => RenewalLifecycleStateMachine::VALIDITY_EXPIRED,
                    'from_workflow_status' => $inst->current_workflow_status,
                    'to_workflow_status' => $inst->current_workflow_status,
                    'notes' => "Instrument expired as of {$evalDate->toDateString()} (expiry date: {$inst->expiry_date?->toDateString()}).",
                    'metadata' => [
                        'expiry_date' => $inst->expiry_date?->toDateString(),
                        'evaluated_at' => $evalDate->toDateString(),
                    ],
                    'created_at' => Carbon::now(),
                ]);

                $count++;
            });
        }

        return $count;
    }

    /**
     * Synchronize future renewal deadlines after a regulatory rule amendment WITHOUT corrupting historical renewal attempts or completed instruments.
     */
    public function synchronizeRuleAmendmentForOrganisation(
        Organisation $organisation,
        RuleVersion $newRuleVersion,
        CarbonInterface|string|null $referenceDate = null
    ): int {
        $refDate = $referenceDate
            ? Carbon::parse($referenceDate, RenewalDeadlineCalculator::TIMEZONE)->startOfDay()
            : Carbon::now(RenewalDeadlineCalculator::TIMEZONE)->startOfDay();

        $effectiveFrom = $newRuleVersion->effective_from
            ? Carbon::parse($newRuleVersion->effective_from, RenewalDeadlineCalculator::TIMEZONE)->startOfDay()
            : $refDate;

        $instruments = StatutoryInstrument::withoutGlobalScopes()
            ->where('tenant_id', $organisation->tenant_id)
            ->where('organisation_id', $organisation->id)
            ->whereNotIn('validity_status', [
                RenewalLifecycleStateMachine::VALIDITY_CANCELLED,
                RenewalLifecycleStateMachine::VALIDITY_SUPERSEDED,
            ])
            ->with(['obligationDefinition', 'activeRenewalAttempt'])
            ->get();

        $updatedCount = 0;

        foreach ($instruments as $inst) {
            // Only apply if the instrument's expiry or issue date falls on or after the rule's effective_from date
            $relevantDate = $inst->expiry_date ?? $inst->issue_date;
            if ($relevantDate !== null && Carbon::parse($relevantDate, RenewalDeadlineCalculator::TIMEZONE)->lt($effectiveFrom)) {
                continue;
            }

            $calc = $this->deadlineCalculator->calculate(
                instrumentDates: [
                    'issue_date' => $inst->issue_date,
                    'expiry_date' => $inst->expiry_date,
                    'source_citation' => $newRuleVersion->source?->citation ?? $inst->source_citation,
                ],
                ruleVersion: $newRuleVersion,
                definition: $inst->obligationDefinition,
                referenceDate: $refDate
            );

            // Preserve established instrument issue_date & expiry_date; only update forward-looking renewal window and statutory deadline
            $inst->update([
                'rule_version_id' => $newRuleVersion->id,
                'renewal_window_opens_at' => $calc['renewal_window_opens_at'],
                'statutory_renewal_deadline' => $calc['statutory_renewal_deadline'],
                'deadline_verification_status' => $calc['deadline_verification_status'],
                'deadline_calculation_basis' => $calc['deadline_calculation_basis'],
                'source_citation' => $calc['source_citation'],
            ]);

            // Note: Historical completed/rejected RenewalAttempts are NEVER modified!
            $updatedCount++;
        }

        return $updatedCount;
    }

    /**
     * Resolve an approved, effective-dated RuleVersion for an ObligationDefinition.
     */
    protected function resolveApprovedRuleVersionForDefinition(
        ObligationDefinition $definition,
        CarbonInterface|string|null $effectiveDate = null
    ): ?RuleVersion {
        $date = $effectiveDate
            ? Carbon::parse($effectiveDate, RenewalDeadlineCalculator::TIMEZONE)->toDateString()
            : Carbon::now(RenewalDeadlineCalculator::TIMEZONE)->toDateString();

        return RuleVersion::whereHas('rulePack', function ($q) use ($definition) {
            $q->where('authority_id', $definition->authority_id);
        })
            ->where('status', 'ACTIVE')
            ->where('verification_status', 'VERIFIED_OFFICIAL')
            ->whereDate('effective_from', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('effective_to')
                  ->orWhereDate('effective_to', '>=', $date);
            })
            ->with('source')
            ->orderByDesc('effective_from')
            ->first();
    }

    /**
     * Authorize that the actor belongs to the same tenant and has ACTIVE membership in the instrument's organisation.
     */
    public function authorizeInstrumentAccess(StatutoryInstrument $instrument, User $actor): void
    {
        $membership = Membership::where('organisation_id', $instrument->organisation_id)
            ->where('user_id', $actor->id)
            ->where('status', 'ACTIVE')
            ->first();

        if (!$membership || $membership->tenant_id !== $instrument->tenant_id) {
            throw new AuthorizationException(
                'Unauthorized access: You do not have active membership in the responsible organisation.'
            );
        }
    }

    /**
     * Authorize that the actor is an Owner, Admin, or Compliance Officer in the instrument's organisation.
     */
    public function authorizeInstrumentAdmin(StatutoryInstrument $instrument, User $actor): void
    {
        $membership = Membership::where('organisation_id', $instrument->organisation_id)
            ->where('user_id', $actor->id)
            ->where('status', 'ACTIVE')
            ->first();

        $allowedRoles = ['OWNER', 'ADMIN', 'COMPLIANCE_OFFICER'];

        if (!$membership || $membership->tenant_id !== $instrument->tenant_id || !in_array($membership->role, $allowedRoles, true)) {
            throw new AuthorizationException(
                'Administrative or Compliance Officer privileges are required for this action.'
            );
        }
    }
}

