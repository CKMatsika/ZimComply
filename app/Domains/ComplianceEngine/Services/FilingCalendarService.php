<?php

namespace App\Domains\ComplianceEngine\Services;

use App\Domains\AuthAndTenant\Models\AuditEvent;
use App\Domains\AuthAndTenant\Models\Membership;
use App\Domains\AuthAndTenant\Models\Organisation;
use App\Domains\ComplianceEngine\Exceptions\ConcurrentUpdateException;
use App\Domains\ComplianceEngine\Models\FilingOccurrence;
use App\Domains\ComplianceEngine\Models\FilingOccurrenceActivityLog;
use App\Domains\RegistrationEngine\Models\ComplianceDocument;
use App\Domains\RegistrationEngine\Models\RuleVersion;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class FilingCalendarService
{
    public function __construct(
        protected FilingLifecycleStateMachine $stateMachine = new FilingLifecycleStateMachine(),
        protected FilingDisplayClassifier $classifier = new FilingDisplayClassifier(),
        protected FilingScheduleGenerator $scheduleGenerator = new FilingScheduleGenerator(),
        protected FilingDeadlineCalculator $calculator = new FilingDeadlineCalculator()
    ) {}

    /**
     * Get the statutory filing calendar and list view data for an organisation.
     *
     * @param Organisation $organisation
     * @param array<string, mixed> $filters
     * @param CarbonInterface|string|null $referenceDate
     * @return array<string, mixed>
     */
    public function getCalendarForOrganisation(
        Organisation $organisation,
        array $filters = [],
        CarbonInterface|string|null $referenceDate = null
    ): array {
        $refDate = $referenceDate
            ? Carbon::parse($referenceDate, FilingDeadlineCalculator::TIMEZONE)->startOfDay()
            : Carbon::now(FilingDeadlineCalculator::TIMEZONE)->startOfDay();

        // Ensure occurrences are generated for active recurring obligations if none exist yet
        $hasAnyOccurrences = FilingOccurrence::withoutGlobalScopes()
            ->where('organisation_id', $organisation->id)
            ->exists();

        if (!$hasAnyOccurrences) {
            $this->scheduleGenerator->generateForOrganisation($organisation, $refDate->copy()->startOfMonth(), 12);
        }

        $query = FilingOccurrence::withoutGlobalScopes()
            ->where('tenant_id', $organisation->tenant_id)
            ->where('organisation_id', $organisation->id)
            ->with([
                'obligationDefinition.authority',
                'obligationDefinition.source',
                'obligationInstance',
                'ruleVersion.source',
                'assignedTo',
                'internallyApprovedBy',
                'submittedBy',
                'acknowledgedBy',
                'documents',
            ]);

        // Filter: Month (YYYY-MM) — applies when viewing a specific month or filtering by month
        if (!empty($filters['month']) && preg_match('/^(\d{4})-(\d{2})$/', (string) $filters['month'], $m)) {
            $mStart = Carbon::create((int) $m[1], (int) $m[2], 1, 0, 0, 0, FilingDeadlineCalculator::TIMEZONE)->startOfMonth();
            $mEnd = $mStart->copy()->endOfMonth();

            $query->where(function (Builder $q) use ($mStart, $mEnd) {
                $q->whereBetween('current_due_date', [$mStart->toDateString(), $mEnd->toDateString()])
                  ->orWhere(function (Builder $sub) use ($mStart, $mEnd) {
                      $sub->whereNull('current_due_date')
                          ->whereBetween('filing_period_end', [$mStart->toDateString(), $mEnd->toDateString()]);
                  });
            });
        }

        // Filter: Regulatory Authority
        if (!empty($filters['authority'])) {
            $authFilter = $filters['authority'];
            $query->whereHas('obligationDefinition.authority', function (Builder $q) use ($authFilter) {
                $q->where('id', $authFilter)->orWhere('code', $authFilter);
            });
        }

        // Filter: Obligation Definition or Instance
        if (!empty($filters['obligation'])) {
            $obFilter = $filters['obligation'];
            $query->where(function (Builder $q) use ($obFilter) {
                $q->where('obligation_instance_id', $obFilter)
                  ->orWhere('obligation_definition_id', $obFilter)
                  ->orWhereHas('obligationDefinition', function (Builder $sub) use ($obFilter) {
                      $sub->where('code', $obFilter);
                  });
            });
        }

        // Filter: Responsible User
        if (!empty($filters['responsible_user'])) {
            if ($filters['responsible_user'] === 'unassigned') {
                $query->whereNull('assigned_to_user_id');
            } else {
                $query->where('assigned_to_user_id', $filters['responsible_user']);
            }
        }

        // Filter: Workflow Status
        if (!empty($filters['workflow_status'])) {
            $query->where('workflow_status', strtoupper((string) $filters['workflow_status']));
        }

        // Filter: Search
        if (!empty($filters['search'])) {
            $term = '%' . strtolower((string) $filters['search']) . '%';
            $query->where(function (Builder $q) use ($term) {
                $q->whereRaw('LOWER(filing_period_label) LIKE ?', [$term])
                  ->orWhereRaw('LOWER(submission_reference) LIKE ?', [$term])
                  ->orWhereRaw('LOWER(source_citation) LIKE ?', [$term])
                  ->orWhereHas('obligationDefinition', function (Builder $sub) use ($term) {
                      $sub->whereRaw('LOWER(title) LIKE ?', [$term])
                          ->orWhereRaw('LOWER(code) LIKE ?', [$term]);
                  })
                  ->orWhereHas('obligationDefinition.authority', function (Builder $sub) use ($term) {
                      $sub->whereRaw('LOWER(name) LIKE ?', [$term])
                          ->orWhereRaw('LOWER(code) LIKE ?', [$term]);
                  });
            });
        }

        $occurrences = $query
            ->orderByRaw('CASE WHEN current_due_date IS NULL THEN 1 ELSE 0 END ASC')
            ->orderBy('current_due_date', 'asc')
            ->orderBy('filing_period_start', 'asc')
            ->get();

        $formatted = $occurrences->map(fn (FilingOccurrence $occ) => $this->formatOccurrenceSummary($occ, $refDate));

        // Filter by dynamic display classification if requested (e.g. OVERDUE, DUE_SOON, UPCOMING, DEADLINE_UNVERIFIED, COMPLETED)
        if (!empty($filters['classification'])) {
            $targetClass = strtoupper((string) $filters['classification']);
            $formatted = $formatted->filter(fn (array $item) => $item['display_classification']['classification'] === $targetClass)->values();
        }

        // Calculate metrics across all organisation occurrences
        $allOrgOccurrences = FilingOccurrence::withoutGlobalScopes()
            ->where('tenant_id', $organisation->tenant_id)
            ->where('organisation_id', $organisation->id)
            ->get();

        $classifiedAll = $allOrgOccurrences->map(fn (FilingOccurrence $o) => [
            'occ' => $o,
            'class' => $this->classifier->classify($o, $refDate)['classification'],
        ]);

        $metrics = [
            'total' => $allOrgOccurrences->count(),
            'upcoming' => $classifiedAll->where('class', FilingDisplayClassifier::UPCOMING)->count(),
            'due_soon' => $classifiedAll->where('class', FilingDisplayClassifier::DUE_SOON)->count(),
            'due_today' => $classifiedAll->where('class', FilingDisplayClassifier::DUE_TODAY)->count(),
            'overdue' => $classifiedAll->where('class', FilingDisplayClassifier::OVERDUE)->count(),
            'completed' => $classifiedAll->where('class', FilingDisplayClassifier::COMPLETED)->count(),
            'deadline_unverified' => $classifiedAll->where('class', FilingDisplayClassifier::DEADLINE_UNVERIFIED)->count(),
            'submitted_internal' => $allOrgOccurrences->where('workflow_status', FilingLifecycleStateMachine::SUBMITTED)->count(),
            'officially_acknowledged' => $allOrgOccurrences->where('workflow_status', FilingLifecycleStateMachine::ACKNOWLEDGED)->count(),
        ];

        // Fetch assignable members of this organisation
        $members = Membership::where('organisation_id', $organisation->id)
            ->where('status', 'ACTIVE')
            ->with('user')
            ->get()
            ->map(fn ($m) => [
                'id' => $m->user->id,
                'name' => $m->user->name,
                'email' => $m->user->email,
                'role' => $m->role,
            ]);

        return [
            'occurrences' => $formatted->values()->all(),
            'metrics' => $metrics,
            'members' => $members,
            'filters' => $filters,
            'reference_date' => $refDate->toDateString(),
            'timezone' => FilingDeadlineCalculator::TIMEZONE,
        ];
    }

    /**
     * Format a single FilingOccurrence for calendar / list display.
     *
     * @return array<string, mixed>
     */
    public function formatOccurrenceSummary(
        FilingOccurrence $occurrence,
        CarbonInterface|string|null $referenceDate = null
    ): array {
        $def = $occurrence->obligationDefinition;
        $auth = $def?->authority;
        $rule = $occurrence->ruleVersion;
        $displayClass = $this->classifier->classify($occurrence, $referenceDate);

        $linkedDocs = $occurrence->documents ?? collect();
        $verifiedDocsCount = $linkedDocs->where('verification_status', 'VERIFIED')->count();
        $pendingDocsCount = $linkedDocs->where('verification_status', 'PENDING')->count();
        $rejectedDocsCount = $linkedDocs->where('verification_status', 'REJECTED')->count();

        $derivedEvidenceState = $this->derivePeriodEvidenceState($occurrence, $linkedDocs);

        return [
            'id' => $occurrence->id,
            'obligation_instance_id' => $occurrence->obligation_instance_id,
            'obligation_definition_id' => $occurrence->obligation_definition_id,
            'title' => $def?->title ?? 'Statutory Filing',
            'obligation_code' => $def?->code ?? 'UNKNOWN',
            'authority' => [
                'id' => $auth?->id,
                'code' => $auth?->code ?? 'GOV',
                'name' => $auth?->name ?? 'Statutory Regulator',
                'portal_url' => $auth?->portal_url,
            ],
            'schedule_identity' => $occurrence->schedule_identity,
            'filing_frequency' => $occurrence->filing_frequency,
            'filing_period' => [
                'start' => $occurrence->filing_period_start?->toDateString(),
                'end' => $occurrence->filing_period_end?->toDateString(),
                'label' => $occurrence->filing_period_label ?? ($occurrence->filing_period_start?->format('M Y') ?? 'Period'),
            ],
            'due_dates' => [
                'original_statutory_due_date' => $occurrence->original_statutory_due_date?->toDateString(),
                'current_due_date' => $occurrence->current_due_date?->toDateString(),
                'payment_due_date' => $occurrence->payment_due_date?->toDateString(),
                'timezone' => $occurrence->timezone,
                'is_unverified' => $occurrence->isDeadlineUnverified(),
            ],
            'deadline_calculation_basis' => $occurrence->deadline_calculation_basis ?? [],
            'display_classification' => $displayClass,
            'workflow_status' => $occurrence->workflow_status,
            'workflow_status_label' => $this->stateMachine->getStatusLabel($occurrence->workflow_status),
            'official_submission_status' => $occurrence->official_submission_status,
            'official_submission_status_label' => $this->stateMachine->getOfficialStatusLabel($occurrence->official_submission_status),
            'submission_reference' => $occurrence->submission_reference,
            'official_receipt_number' => $occurrence->official_receipt_number,
            'responsible_user' => $occurrence->assignedTo ? [
                'id' => $occurrence->assignedTo->id,
                'name' => $occurrence->assignedTo->name,
                'email' => $occurrence->assignedTo->email,
            ] : null,
            'evidence_status' => $derivedEvidenceState,
            'evidence_summary' => [
                'total_linked' => $linkedDocs->count(),
                'verified' => $verifiedDocsCount,
                'pending' => $pendingDocsCount,
                'rejected' => $rejectedDocsCount,
                'required_documents' => $def?->required_documents ?? [],
            ],
            'source_governance' => [
                'source_citation' => $occurrence->source_citation ?? $def?->statutory_citation,
                'verification_status' => $occurrence->verification_status,
                'is_verified' => $occurrence->verification_status === 'VERIFIED_OFFICIAL',
                'rule_version_id' => $occurrence->rule_version_id,
                'rule_version_code' => $rule?->version_code ?? ($occurrence->deadline_calculation_basis['rule_version_code'] ?? null),
                'effective_from' => $rule?->effective_from?->toDateString() ?? ($occurrence->deadline_calculation_basis['effective_from'] ?? null),
            ],
            'timestamps' => [
                'internally_approved_at' => $occurrence->internally_approved_at?->toIso8601String(),
                'internally_approved_by' => $occurrence->internallyApprovedBy?->name,
                'submitted_at' => $occurrence->submitted_at?->toIso8601String(),
                'submitted_by' => $occurrence->submittedBy?->name,
                'acknowledged_at' => $occurrence->acknowledged_at?->toIso8601String(),
                'acknowledged_by' => $occurrence->acknowledgedBy?->name,
                'closed_at' => $occurrence->closed_at?->toIso8601String(),
                'closed_by' => $occurrence->closedBy?->name,
            ],
            'internal_notes' => $occurrence->internal_notes,
            'is_obligation_currently_applicable' => $occurrence->is_obligation_currently_applicable,
            'lock_version' => $occurrence->lock_version,
        ];
    }

    /**
     * Get detailed filing occurrence payload with linked period-specific documents,
     * available transitions, and full audit history.
     *
     * @return array<string, mixed>
     */
    public function getOccurrenceDetail(FilingOccurrence $occurrence, User $viewer): array
    {
        $this->authorizeOccurrenceAccess($occurrence, $viewer);

        $occurrence->loadMissing([
            'obligationDefinition.authority',
            'obligationDefinition.source',
            'obligationInstance',
            'ruleVersion.source',
            'assignedTo',
            'internallyApprovedBy',
            'submittedBy',
            'acknowledgedBy',
            'closedBy',
            'documents.uploader',
            'documents.verifiedBy',
            'activityLogs.user',
            'activityLogs.fromUser',
            'activityLogs.toUser',
        ]);

        $summary = $this->formatOccurrenceSummary($occurrence);
        $transitions = $this->stateMachine->getAvailableTransitions($occurrence, $viewer);

        $now = Carbon::now(FilingDeadlineCalculator::TIMEZONE)->toDateString();
        $formattedDocs = $occurrence->documents->map(function (ComplianceDocument $doc) use ($now) {
            $isExpired = ($doc->verification_status === 'EXPIRED')
                || ($doc->expiry_date && $doc->expiry_date->toDateString() < $now);

            return [
                'id' => $doc->id,
                'title' => $doc->title,
                'file_name' => $doc->file_name,
                'document_type' => $doc->document_type,
                'verification_status' => $doc->verification_status,
                'is_verified' => ($doc->verification_status === 'VERIFIED'),
                'is_pending' => ($doc->verification_status === 'PENDING'),
                'is_rejected' => ($doc->verification_status === 'REJECTED'),
                'is_expired' => $isExpired,
                'rejection_reason' => $doc->rejection_reason,
                'expiry_date' => $doc->expiry_date?->toDateString(),
                'uploaded_at' => $doc->created_at?->toIso8601String(),
                'uploader_name' => $doc->uploader?->name,
                'verified_by_name' => $doc->verifiedBy?->name,
                'verified_at' => $doc->verified_at?->toIso8601String(),
            ];
        });

        // Also fetch organisation documents that could be linked to this occurrence
        $availableOrgDocs = ComplianceDocument::withoutGlobalScopes()
            ->where('tenant_id', $occurrence->tenant_id)
            ->where('organisation_id', $occurrence->organisation_id)
            ->where(function ($q) use ($occurrence) {
                $q->whereNull('filing_occurrence_id')
                  ->orWhere('filing_occurrence_id', '!=', $occurrence->id);
            })
            ->latest('created_at')
            ->limit(25)
            ->get()
            ->map(fn (ComplianceDocument $d) => [
                'id' => $d->id,
                'title' => $d->title,
                'file_name' => $d->file_name,
                'document_type' => $d->document_type,
                'verification_status' => $d->verification_status,
                'filing_occurrence_id' => $d->filing_occurrence_id,
            ]);

        $timeline = $occurrence->activityLogs->map(function (FilingOccurrenceActivityLog $log) {
            return [
                'id' => $log->id,
                'activity_type' => $log->activity_type,
                'from_status' => $log->from_status,
                'to_status' => $log->to_status,
                'from_official_status' => $log->from_official_status,
                'to_official_status' => $log->to_official_status,
                'actor' => $log->user ? [
                    'id' => $log->user->id,
                    'name' => $log->user->name,
                ] : null,
                'from_user' => $log->fromUser ? [
                    'id' => $log->fromUser->id,
                    'name' => $log->fromUser->name,
                ] : null,
                'to_user' => $log->toUser ? [
                    'id' => $log->toUser->id,
                    'name' => $log->toUser->name,
                ] : null,
                'notes' => $log->notes,
                'metadata' => $log->metadata,
                'created_at' => $log->created_at?->toIso8601String(),
            ];
        });

        return [
            'occurrence' => $summary,
            'documents' => $formattedDocs,
            'available_vault_documents' => $availableOrgDocs,
            'available_transitions' => $transitions,
            'timeline' => $timeline,
        ];
    }

    /**
     * Assign or reassign a filing occurrence to an active member of the responsible organisation.
     *
     * @throws AuthorizationException
     * @throws ConcurrentUpdateException
     */
    public function assignOccurrence(
        FilingOccurrence $occurrence,
        ?User $assignee,
        User $actor,
        ?int $lockVersion = null,
        ?string $notes = null
    ): FilingOccurrence {
        $this->authorizeOccurrenceAdmin($occurrence, $actor);

        if ($assignee !== null) {
            $assigneeMembership = Membership::where('organisation_id', $occurrence->organisation_id)
                ->where('user_id', $assignee->id)
                ->where('status', 'ACTIVE')
                ->first();

            if (!$assigneeMembership) {
                throw new AuthorizationException(
                    "Target user [{$assignee->name}] is not an active member of this organisation. Cross-organisation assignments are strictly prohibited."
                );
            }
        }

        return DB::transaction(function () use ($occurrence, $assignee, $actor, $lockVersion, $notes) {
            $locked = FilingOccurrence::withoutGlobalScopes()
                ->where('id', $occurrence->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockVersion !== null && $locked->lock_version !== $lockVersion) {
                throw new ConcurrentUpdateException(
                    'Conflict detected: This filing occurrence has been modified by another user. Please refresh and try again.'
                );
            }

            $previousUserId = $locked->assigned_to_user_id;
            $newUserId = $assignee?->id;

            $locked->update([
                'assigned_to_user_id' => $newUserId,
                'lock_version' => $locked->lock_version + 1,
            ]);

            FilingOccurrenceActivityLog::create([
                'tenant_id' => $locked->tenant_id,
                'organisation_id' => $locked->organisation_id,
                'filing_occurrence_id' => $locked->id,
                'user_id' => $actor->id,
                'activity_type' => 'ASSIGNMENT_CHANGED',
                'from_user_id' => $previousUserId,
                'to_user_id' => $newUserId,
                'notes' => $notes ?? ($newUserId ? "Assigned filing occurrence to {$assignee->name}." : 'Unassigned filing responsibility.'),
                'created_at' => Carbon::now(),
            ]);

            AuditEvent::create([
                'tenant_id' => $locked->tenant_id,
                'user_id' => $actor->id,
                'event_type' => 'FILING_OCCURRENCE_ASSIGNED',
                'auditable_type' => FilingOccurrence::class,
                'auditable_id' => $locked->id,
                'payload' => [
                    'previous_user_id' => $previousUserId,
                    'new_user_id' => $newUserId,
                    'filing_period_label' => $locked->filing_period_label,
                ],
            ]);

            return $locked->fresh(['assignedTo', 'activityLogs.user']);
        });
    }

    /**
     * Transition a filing occurrence's internal workflow status.
     *
     * Crucial Safeguard:
     * - Transitioning to SUBMITTED with a user-entered `submission_reference` sets
     *   `official_submission_status = 'DECLARED_SUBMITTED'`. It NEVER automatically sets
     *   `OFFICIALLY_ACKNOWLEDGED` or `ACKNOWLEDGED`.
     * - Transitioning to ACKNOWLEDGED requires admin/compliance reviewer role AND
     *   period-specific, human-VERIFIED non-expired supporting evidence.
     *
     * @throws AuthorizationException
     * @throws ConcurrentUpdateException
     */
    public function transitionOccurrenceStatus(
        FilingOccurrence $occurrence,
        string $targetStatus,
        User $actor,
        ?int $lockVersion = null,
        ?string $notes = null,
        ?string $submissionReference = null,
        ?string $officialReceiptNumber = null
    ): FilingOccurrence {
        $this->authorizeOccurrenceAccess($occurrence, $actor);

        $normalizedTarget = strtoupper(trim($targetStatus));

        $this->stateMachine->validateTransition(
            occurrence: $occurrence,
            targetStatus: $normalizedTarget,
            actor: $actor,
            notes: $notes,
            submissionReference: $submissionReference,
            officialReceiptNumber: $officialReceiptNumber
        );

        return DB::transaction(function () use (
            $occurrence,
            $normalizedTarget,
            $actor,
            $lockVersion,
            $notes,
            $submissionReference,
            $officialReceiptNumber
        ) {
            $locked = FilingOccurrence::withoutGlobalScopes()
                ->where('id', $occurrence->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockVersion !== null && $locked->lock_version !== $lockVersion) {
                throw new ConcurrentUpdateException(
                    'Conflict detected: This filing occurrence has been modified by another user. Please refresh and try again.'
                );
            }

            $fromStatus = $locked->workflow_status;
            $fromOfficialStatus = $locked->official_submission_status;
            $newOfficialStatus = $fromOfficialStatus;

            $updates = [
                'workflow_status' => $normalizedTarget,
                'lock_version' => $locked->lock_version + 1,
            ];

            if (!empty($notes)) {
                $updates['internal_notes'] = $notes;
            }

            if ($submissionReference !== null && trim($submissionReference) !== '') {
                $updates['submission_reference'] = trim($submissionReference);
            }

            if ($officialReceiptNumber !== null && trim($officialReceiptNumber) !== '') {
                $updates['official_receipt_number'] = trim($officialReceiptNumber);
            }

            // Track lifecycle timestamps and official status distinction
            if ($normalizedTarget === FilingLifecycleStateMachine::INTERNALLY_APPROVED) {
                $updates['internally_approved_at'] = Carbon::now();
                $updates['internally_approved_by_user_id'] = $actor->id;
            } elseif ($normalizedTarget === FilingLifecycleStateMachine::SUBMITTED) {
                // Internal submission declaration ONLY — does NOT confer official acceptance!
                $updates['submitted_at'] = Carbon::now();
                $updates['submitted_by_user_id'] = $actor->id;
                $newOfficialStatus = FilingLifecycleStateMachine::OFFICIAL_DECLARED_SUBMITTED;
                $updates['official_submission_status'] = $newOfficialStatus;
            } elseif ($normalizedTarget === FilingLifecycleStateMachine::ACKNOWLEDGED) {
                $updates['acknowledged_at'] = Carbon::now();
                $updates['acknowledged_by_user_id'] = $actor->id;
                $newOfficialStatus = FilingLifecycleStateMachine::OFFICIAL_ACKNOWLEDGED;
                $updates['official_submission_status'] = $newOfficialStatus;
                $updates['evidence_status'] = 'OFFICIALLY_ACKNOWLEDGED';
            } elseif ($normalizedTarget === FilingLifecycleStateMachine::REJECTED) {
                if (in_array($fromStatus, [FilingLifecycleStateMachine::SUBMITTED, FilingLifecycleStateMachine::ACKNOWLEDGED], true)) {
                    $newOfficialStatus = FilingLifecycleStateMachine::OFFICIAL_REJECTED;
                    $updates['official_submission_status'] = $newOfficialStatus;
                }
            } elseif ($normalizedTarget === FilingLifecycleStateMachine::CLOSED) {
                $updates['closed_at'] = Carbon::now();
                $updates['closed_by_user_id'] = $actor->id;
            }

            $locked->update($updates);

            // Refresh evidence status based on linked documents
            $linkedDocs = ComplianceDocument::withoutGlobalScopes()
                ->where('organisation_id', $locked->organisation_id)
                ->where('filing_occurrence_id', $locked->id)
                ->get();
            $derivedEvidence = $this->derivePeriodEvidenceState($locked, $linkedDocs);
            if ($derivedEvidence !== $locked->evidence_status) {
                $locked->update(['evidence_status' => $derivedEvidence]);
            }

            FilingOccurrenceActivityLog::create([
                'tenant_id' => $locked->tenant_id,
                'organisation_id' => $locked->organisation_id,
                'filing_occurrence_id' => $locked->id,
                'user_id' => $actor->id,
                'activity_type' => 'STATUS_TRANSITION',
                'from_status' => $fromStatus,
                'to_status' => $normalizedTarget,
                'from_official_status' => $fromOfficialStatus,
                'to_official_status' => $newOfficialStatus,
                'notes' => $notes,
                'metadata' => [
                    'submission_reference' => $locked->submission_reference,
                    'official_receipt_number' => $locked->official_receipt_number,
                ],
                'created_at' => Carbon::now(),
            ]);

            AuditEvent::create([
                'tenant_id' => $locked->tenant_id,
                'user_id' => $actor->id,
                'event_type' => 'FILING_OCCURRENCE_STATUS_TRANSITION',
                'auditable_type' => FilingOccurrence::class,
                'auditable_id' => $locked->id,
                'payload' => [
                    'from_status' => $fromStatus,
                    'to_status' => $normalizedTarget,
                    'from_official_status' => $fromOfficialStatus,
                    'to_official_status' => $newOfficialStatus,
                    'submission_reference' => $locked->submission_reference,
                    'notes' => $notes,
                ],
            ]);

            return $locked->fresh(['assignedTo', 'documents', 'activityLogs.user']);
        });
    }

    /**
     * Link a compliance document to a specific filing occurrence period.
     *
     * Linking evidence to a Jan 2026 filing occurrence proves/supports ONLY Jan 2026,
     * NOT Feb 2026 or every recurring filing.
     * Furthermore, linking a document NEVER automatically declares compliance or official acknowledgement.
     */
    public function linkEvidenceToOccurrence(
        FilingOccurrence $occurrence,
        ComplianceDocument $document,
        User $actor
    ): FilingOccurrence {
        $this->authorizeOccurrenceAccess($occurrence, $actor);

        if ($document->tenant_id !== $occurrence->tenant_id || $document->organisation_id !== $occurrence->organisation_id) {
            throw new AuthorizationException('Document and filing occurrence must belong to the same tenant and organisation.');
        }

        return DB::transaction(function () use ($occurrence, $document, $actor) {
            $document->update([
                'obligation_instance_id' => $occurrence->obligation_instance_id,
                'filing_occurrence_id' => $occurrence->id,
            ]);

            $linkedDocs = ComplianceDocument::withoutGlobalScopes()
                ->where('organisation_id', $occurrence->organisation_id)
                ->where('filing_occurrence_id', $occurrence->id)
                ->get();

            $newEvidenceState = $this->derivePeriodEvidenceState($occurrence, $linkedDocs);
            $occurrence->update([
                'evidence_status' => $newEvidenceState,
                'lock_version' => $occurrence->lock_version + 1,
            ]);

            FilingOccurrenceActivityLog::create([
                'tenant_id' => $occurrence->tenant_id,
                'organisation_id' => $occurrence->organisation_id,
                'filing_occurrence_id' => $occurrence->id,
                'user_id' => $actor->id,
                'activity_type' => 'EVIDENCE_LINKED',
                'notes' => "Linked supporting document [{$document->file_name}] ({$document->verification_status}) to filing period {$occurrence->filing_period_label}.",
                'metadata' => [
                    'document_id' => $document->id,
                    'file_name' => $document->file_name,
                    'verification_status' => $document->verification_status,
                    'derived_evidence_status' => $newEvidenceState,
                ],
                'created_at' => Carbon::now(),
            ]);

            AuditEvent::create([
                'tenant_id' => $occurrence->tenant_id,
                'user_id' => $actor->id,
                'event_type' => 'DOCUMENT_LINKED_TO_FILING_OCCURRENCE',
                'auditable_type' => FilingOccurrence::class,
                'auditable_id' => $occurrence->id,
                'payload' => [
                    'document_id' => $document->id,
                    'filing_period_label' => $occurrence->filing_period_label,
                    'derived_evidence_status' => $newEvidenceState,
                ],
            ]);

            return $occurrence->fresh(['documents', 'activityLogs.user']);
        });
    }

    /**
     * Unlink a compliance document from a filing occurrence.
     */
    public function unlinkEvidenceFromOccurrence(
        FilingOccurrence $occurrence,
        ComplianceDocument $document,
        User $actor
    ): FilingOccurrence {
        $this->authorizeOccurrenceAccess($occurrence, $actor);

        if ($document->filing_occurrence_id !== $occurrence->id) {
            throw new InvalidArgumentException('Document is not linked to this filing occurrence.');
        }

        return DB::transaction(function () use ($occurrence, $document, $actor) {
            $document->update([
                'filing_occurrence_id' => null,
            ]);

            $linkedDocs = ComplianceDocument::withoutGlobalScopes()
                ->where('organisation_id', $occurrence->organisation_id)
                ->where('filing_occurrence_id', $occurrence->id)
                ->get();

            $newEvidenceState = $this->derivePeriodEvidenceState($occurrence, $linkedDocs);
            $occurrence->update([
                'evidence_status' => $newEvidenceState,
                'lock_version' => $occurrence->lock_version + 1,
            ]);

            FilingOccurrenceActivityLog::create([
                'tenant_id' => $occurrence->tenant_id,
                'organisation_id' => $occurrence->organisation_id,
                'filing_occurrence_id' => $occurrence->id,
                'user_id' => $actor->id,
                'activity_type' => 'EVIDENCE_UNLINKED',
                'notes' => "Unlinked document [{$document->file_name}] from filing period {$occurrence->filing_period_label}.",
                'metadata' => [
                    'document_id' => $document->id,
                    'file_name' => $document->file_name,
                    'derived_evidence_status' => $newEvidenceState,
                ],
                'created_at' => Carbon::now(),
            ]);

            return $occurrence->fresh(['documents', 'activityLogs.user']);
        });
    }

    /**
     * Authorised statutory due-date correction workflow.
     *
     * Security & Data Integrity Safeguard (Section 10):
     * - User-controlled dates can NEVER silently override statutory due dates.
     * - Corrections are only allowed by an Organisation Admin / Compliance Officer
     *   with an explicit verified `RuleVersion` or documented legal basis citation and justification note,
     *   and `original_statutory_due_date` is permanently preserved.
     */
    public function recordAuthorisedDueDateCorrection(
        FilingOccurrence $occurrence,
        string $newDueDate,
        string $legalBasisCitation,
        string $justificationNotes,
        User $actor,
        ?string $ruleVersionId = null,
        ?int $lockVersion = null
    ): FilingOccurrence {
        $this->authorizeOccurrenceAdmin($occurrence, $actor);

        if (empty(trim($legalBasisCitation)) || empty(trim($justificationNotes))) {
            throw new InvalidArgumentException(
                'Legal audit requirement: Both a statutory legal basis citation and a detailed justification note are mandatory to amend a due date.'
            );
        }

        if ($occurrence->isCompleted()) {
            throw new InvalidArgumentException(
                'Historical immutability safeguard: Cannot modify the due date of a completed historical filing occurrence.'
            );
        }

        $ruleVersion = null;
        if ($ruleVersionId !== null) {
            $ruleVersion = RuleVersion::findOrFail($ruleVersionId);
            if (!$ruleVersion->isVerified()) {
                throw new InvalidArgumentException('Cannot apply due date amendment from an unverified rule version.');
            }
        }

        $parsedNewDate = Carbon::parse($newDueDate, FilingDeadlineCalculator::TIMEZONE)->toDateString();

        return DB::transaction(function () use (
            $occurrence,
            $parsedNewDate,
            $legalBasisCitation,
            $justificationNotes,
            $actor,
            $ruleVersion,
            $lockVersion
        ) {
            $locked = FilingOccurrence::withoutGlobalScopes()
                ->where('id', $occurrence->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockVersion !== null && $locked->lock_version !== $lockVersion) {
                throw new ConcurrentUpdateException('Concurrent modification detected.');
            }

            $previousDueDate = $locked->current_due_date?->toDateString();
            $basis = (array) ($locked->deadline_calculation_basis ?? []);
            $basis['authorised_amendments'][] = [
                'previous_due_date' => $previousDueDate,
                'amended_due_date' => $parsedNewDate,
                'legal_basis_citation' => $legalBasisCitation,
                'justification' => $justificationNotes,
                'rule_version_id' => $ruleVersion?->id,
                'amended_by_user_id' => $actor->id,
                'amended_at' => Carbon::now(FilingDeadlineCalculator::TIMEZONE)->toIso8601String(),
            ];

            // Note: original_statutory_due_date is NEVER overwritten
            $locked->update([
                'current_due_date' => $parsedNewDate,
                'rule_version_id' => $ruleVersion ? $ruleVersion->id : $locked->rule_version_id,
                'deadline_calculation_basis' => $basis,
                'lock_version' => $locked->lock_version + 1,
            ]);

            FilingOccurrenceActivityLog::create([
                'tenant_id' => $locked->tenant_id,
                'organisation_id' => $locked->organisation_id,
                'filing_occurrence_id' => $locked->id,
                'user_id' => $actor->id,
                'activity_type' => 'DUE_DATE_AMENDED',
                'notes' => "Authorised statutory due date correction ({$previousDueDate} -> {$parsedNewDate}). Legal basis: {$legalBasisCitation}. Reason: {$justificationNotes}",
                'metadata' => [
                    'original_statutory_due_date' => $locked->original_statutory_due_date?->toDateString(),
                    'previous_due_date' => $previousDueDate,
                    'new_due_date' => $parsedNewDate,
                    'legal_basis_citation' => $legalBasisCitation,
                ],
                'created_at' => Carbon::now(),
            ]);

            AuditEvent::create([
                'tenant_id' => $locked->tenant_id,
                'user_id' => $actor->id,
                'event_type' => 'FILING_DUE_DATE_AMENDED',
                'auditable_type' => FilingOccurrence::class,
                'auditable_id' => $locked->id,
                'payload' => [
                    'original_statutory_due_date' => $locked->original_statutory_due_date?->toDateString(),
                    'previous_due_date' => $previousDueDate,
                    'new_due_date' => $parsedNewDate,
                    'legal_basis_citation' => $legalBasisCitation,
                    'justification' => $justificationNotes,
                ],
            ]);

            return $locked->fresh(['activityLogs.user']);
        });
    }

    /**
     * Derive the granular evidence state for a filing occurrence:
     * Preserves the distinction between:
     * - NONE
     * - DOCUMENT_UPLOADED (pending human review)
     * - HUMAN_REVIEWED (verified by human reviewer)
     * - INTERNALLY_SUBMITTED (human reviewed + declared submitted)
     * - OFFICIALLY_ACKNOWLEDGED (human reviewed + official acceptance confirmed)
     * - REJECTED_EVIDENCE
     */
    public function derivePeriodEvidenceState(FilingOccurrence $occurrence, $linkedDocs): string
    {
        if ($occurrence->workflow_status === FilingLifecycleStateMachine::ACKNOWLEDGED
            || $occurrence->official_submission_status === FilingLifecycleStateMachine::OFFICIAL_ACKNOWLEDGED) {
            return 'OFFICIALLY_ACKNOWLEDGED';
        }

        if ($linkedDocs->isEmpty()) {
            return $occurrence->workflow_status === FilingLifecycleStateMachine::SUBMITTED
                ? 'INTERNALLY_SUBMITTED'
                : 'NONE';
        }

        $now = Carbon::now(FilingDeadlineCalculator::TIMEZONE)->toDateString();
        $hasVerifiedValid = $linkedDocs->contains(function (ComplianceDocument $doc) use ($now) {
            if ($doc->verification_status !== 'VERIFIED') {
                return false;
            }
            if ($doc->expiry_date !== null && $doc->expiry_date->toDateString() < $now) {
                return false;
            }
            return true;
        });

        if ($hasVerifiedValid) {
            if ($occurrence->workflow_status === FilingLifecycleStateMachine::SUBMITTED) {
                return 'INTERNALLY_SUBMITTED';
            }
            return 'HUMAN_REVIEWED';
        }

        $hasPending = $linkedDocs->contains(fn (ComplianceDocument $doc) => $doc->verification_status === 'PENDING');
        if ($hasPending) {
            return 'DOCUMENT_UPLOADED';
        }

        $allRejected = $linkedDocs->every(fn (ComplianceDocument $doc) => $doc->verification_status === 'REJECTED');
        if ($allRejected) {
            return 'REJECTED_EVIDENCE';
        }

        return 'DOCUMENT_UPLOADED';
    }

    /**
     * Authorize that the user belongs to the same tenant and organisation as the filing occurrence.
     */
    public function authorizeOccurrenceAccess(FilingOccurrence $occurrence, User $user): void
    {
        $membership = Membership::where('organisation_id', $occurrence->organisation_id)
            ->where('user_id', $user->id)
            ->where('status', 'ACTIVE')
            ->first();

        if (!$membership || $membership->tenant_id !== $occurrence->tenant_id) {
            throw new AuthorizationException(
                'Unauthorized access: You do not have active membership in the responsible organisation.'
            );
        }
    }

    /**
     * Authorize that the user is an administrator, owner, or compliance officer of the organisation.
     */
    public function authorizeOccurrenceAdmin(FilingOccurrence $occurrence, User $user): void
    {
        $membership = Membership::where('organisation_id', $occurrence->organisation_id)
            ->where('user_id', $user->id)
            ->where('status', 'ACTIVE')
            ->first();

        $allowedRoles = ['OWNER', 'ADMIN', 'COMPLIANCE_OFFICER'];

        if (!$membership || $membership->tenant_id !== $occurrence->tenant_id || !in_array($membership->role, $allowedRoles, true)) {
            throw new AuthorizationException(
                'Administrative privileges required to modify filing assignments or statutory schedule parameters.'
            );
        }
    }
}
