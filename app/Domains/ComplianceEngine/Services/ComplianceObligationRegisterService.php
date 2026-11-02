<?php

namespace App\Domains\ComplianceEngine\Services;

use App\Domains\AuthAndTenant\Models\AuditEvent;
use App\Domains\AuthAndTenant\Models\Membership;
use App\Domains\AuthAndTenant\Models\Organisation;
use App\Domains\ComplianceEngine\Exceptions\ConcurrentUpdateException;
use App\Domains\RegistrationEngine\Models\ComplianceDocument;
use App\Domains\RegistrationEngine\Models\ObligationActivityLog;
use App\Domains\RegistrationEngine\Models\ObligationInstance;
use App\Domains\RegistrationEngine\Services\ObligationEvidenceStatusEvaluator;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ComplianceObligationRegisterService
{
    public function __construct(
        protected ComplianceLifecycleStateMachine $stateMachine,
        protected ObligationEvidenceStatusEvaluator $evidenceStatusEvaluator
    ) {}

    /**
     * Get the complete, filtered compliance register for an organisation.
     *
     * @param Organisation $organisation
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function getRegisterForOrganisation(Organisation $organisation, array $filters = []): array
    {
        $query = ObligationInstance::withoutGlobalScopes()
            ->where('organisation_id', $organisation->id)
            ->with([
                'definition.authority',
                'definition.source',
                'definition.rulePack',
                'assessmentResult.ruleVersion.source',
                'assignedTo',
                'lastReviewedBy',
                'documents',
            ]);

        // Filter: Authority
        if (!empty($filters['authority'])) {
            $authFilter = $filters['authority'];
            $query->whereHas('definition.authority', function (Builder $q) use ($authFilter) {
                $q->where('id', $authFilter)->orWhere('code', $authFilter);
            });
        }

        // Filter: Category / Legal Classification
        if (!empty($filters['category'])) {
            $catFilter = $filters['category'];
            $query->whereHas('definition', function (Builder $q) use ($catFilter) {
                $q->where('legal_classification', $catFilter);
            });
        }

        // Filter: Lifecycle Status
        if (!empty($filters['lifecycle_status'])) {
            $statusFilter = $this->stateMachine->normalizeStatus($filters['lifecycle_status']);
            $query->where(function (Builder $q) use ($statusFilter) {
                $q->where('status', $statusFilter);
                // Also match legacy status aliases
                if ($statusFilter === ComplianceLifecycleStateMachine::VERIFIED_COMPLETE) {
                    $q->orWhere('status', 'COMPLIANT');
                } elseif ($statusFilter === ComplianceLifecycleStateMachine::NOT_APPLICABLE) {
                    $q->orWhere('status', 'EXEMPT');
                } elseif ($statusFilter === ComplianceLifecycleStateMachine::UNDER_REVIEW) {
                    $q->orWhere('status', 'AWAITING_VERIFICATION');
                } elseif ($statusFilter === ComplianceLifecycleStateMachine::BLOCKED) {
                    $q->orWhere('status', 'DISPUTED');
                }
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

        // Search: Code, Title, Citation, Authority Name
        if (!empty($filters['search'])) {
            $term = '%' . strtolower($filters['search']) . '%';
            $query->where(function (Builder $q) use ($term) {
                $q->whereHas('definition', function (Builder $sub) use ($term) {
                    $sub->whereRaw('LOWER(title) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(code) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(statutory_citation) LIKE ?', [$term]);
                })->orWhereHas('definition.authority', function (Builder $sub) use ($term) {
                    $sub->whereRaw('LOWER(name) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(code) LIKE ?', [$term]);
                });
            });
        }

        $allInstances = $query->orderBy('created_at', 'asc')->get();

        // Calculate summary metrics across the organisation's complete set of obligations
        $totalQuery = ObligationInstance::withoutGlobalScopes()->where('organisation_id', $organisation->id)->get();
        $today = Carbon::now()->toDateString();

        $metrics = [
            'total' => $totalQuery->count(),
            'not_started' => $totalQuery->filter(fn ($i) => $this->stateMachine->normalizeStatus($i->status) === ComplianceLifecycleStateMachine::NOT_STARTED)->count(),
            'in_progress' => $totalQuery->filter(fn ($i) => $this->stateMachine->normalizeStatus($i->status) === ComplianceLifecycleStateMachine::IN_PROGRESS)->count(),
            'awaiting_evidence' => $totalQuery->filter(fn ($i) => $this->stateMachine->normalizeStatus($i->status) === ComplianceLifecycleStateMachine::AWAITING_EVIDENCE)->count(),
            'under_review' => $totalQuery->filter(fn ($i) => $this->stateMachine->normalizeStatus($i->status) === ComplianceLifecycleStateMachine::UNDER_REVIEW)->count(),
            'completed_internal' => $totalQuery->filter(fn ($i) => $this->stateMachine->normalizeStatus($i->status) === ComplianceLifecycleStateMachine::COMPLETED_INTERNAL)->count(),
            'verified_complete' => $totalQuery->filter(fn ($i) => $this->stateMachine->normalizeStatus($i->status) === ComplianceLifecycleStateMachine::VERIFIED_COMPLETE)->count(),
            'blocked' => $totalQuery->filter(fn ($i) => $this->stateMachine->normalizeStatus($i->status) === ComplianceLifecycleStateMachine::BLOCKED)->count(),
            'not_applicable' => $totalQuery->filter(fn ($i) => $this->stateMachine->normalizeStatus($i->status) === ComplianceLifecycleStateMachine::NOT_APPLICABLE)->count(),
            'overdue' => $totalQuery->filter(fn ($i) => 
                $i->next_due_date !== null 
                && $i->next_due_date->toDateString() < $today 
                && $this->stateMachine->normalizeStatus($i->status) !== ComplianceLifecycleStateMachine::VERIFIED_COMPLETE
            )->count(),
        ];

        // Format items for response
        $items = $allInstances->map(function (ObligationInstance $instance) {
            return $this->formatObligationSummary($instance);
        });

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
            'obligations' => $items,
            'metrics' => $metrics,
            'members' => $members,
            'filters' => $filters,
        ];
    }

    /**
     * Format an obligation instance for the compliance register list view.
     *
     * @return array<string, mixed>
     */
    public function formatObligationSummary(ObligationInstance $instance): array
    {
        $def = $instance->definition;
        $auth = $def?->authority;
        $res = $instance->assessmentResult;
        $rule = $res?->ruleVersion;
        $normalizedStatus = $this->stateMachine->normalizeStatus($instance->status);

        $linkedDocs = $instance->documents;
        $validVerifiedDocsCount = $linkedDocs->where('verification_status', 'VERIFIED')->count();
        $pendingDocsCount = $linkedDocs->where('verification_status', 'PENDING')->count();
        $rejectedDocsCount = $linkedDocs->where('verification_status', 'REJECTED')->count();
        $expiredDocsCount = $linkedDocs->filter(function (ComplianceDocument $doc) {
            return $doc->verification_status === 'EXPIRED' 
                || ($doc->expiry_date && $doc->expiry_date->toDateString() < Carbon::now()->toDateString());
        })->count();

        return [
            'id' => $instance->id,
            'name' => $def?->title ?? $def?->name ?? 'Statutory Obligation',
            'code' => $def?->code ?? 'UNKNOWN',
            'authority' => [
                'id' => $auth?->id,
                'name' => $auth?->name ?? 'National Authority',
                'code' => $auth?->code ?? 'GOV',
            ],
            'category' => $def?->legal_classification ?? 'MANDATORY_STATUTORY',
            'obligation_type' => $def?->obligation_type ?? 'PERIODIC_RETURN',
            'applicability_decision' => [
                'decision' => $res?->decision ?? 'APPLICABLE',
                'is_unverified_hold' => ($res?->decision === 'UNVERIFIED_HOLD'),
                'is_requires_info' => ($res?->decision === 'REQUIRES_INFORMATION'),
                'trigger_reasons' => $res?->trigger_reasons ?? [],
            ],
            'approved_source' => [
                'citation' => $res?->rule_source_citation ?? $def?->statutory_citation ?? 'Statutes of Zimbabwe',
                'rule_version_id' => $rule?->id,
                'rule_version_number' => $rule?->version_code ?? $rule?->version_number ?? 'v1.0',
                'is_verified' => $rule?->isVerified() ?? true,
                'effective_from' => $rule?->effective_from?->toDateString(),
            ],
            'compliance_frequency' => $instance->compliance_frequency ?? $def?->renewal_frequency ?? 'PERIODIC',
            'responsible_user' => $instance->assignedTo ? [
                'id' => $instance->assignedTo->id,
                'name' => $instance->assignedTo->name,
                'email' => $instance->assignedTo->email,
            ] : null,
            'lifecycle_status' => $normalizedStatus,
            'lifecycle_status_label' => $this->stateMachine->getStatusLabel($normalizedStatus),
            'evidence_status' => $instance->evidence_status,
            'evidence_summary' => [
                'total_linked' => $linkedDocs->count(),
                'verified' => $validVerifiedDocsCount,
                'pending' => $pendingDocsCount,
                'rejected' => $rejectedDocsCount,
                'expired' => $expiredDocsCount,
                'requires_attention' => ($rejectedDocsCount > 0 || $expiredDocsCount > 0),
            ],
            'evidence_requirements' => $def?->required_documents ?? [],
            'relevant_dates' => [
                'next_due_date' => $instance->next_due_date?->toDateString(),
                'last_reviewed_at' => $instance->last_reviewed_at?->toIso8601String(),
                'declared_registered_at' => $instance->declared_registered_at?->toDateString(),
                'last_assessment_date' => $res?->evaluated_at?->toDateString() ?? $instance->created_at?->toDateString(),
            ],
            'outstanding_actions' => $this->determineOutstandingAction($instance, $normalizedStatus, $linkedDocs->count(), $validVerifiedDocsCount),
            'declared_registration_number' => $instance->declared_registration_number,
            'internal_notes' => $instance->internal_notes,
            'lock_version' => $instance->lock_version,
            'created_at' => $instance->created_at?->toIso8601String(),
            'updated_at' => $instance->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Get detailed obligation view with timeline, documents, and available actions.
     *
     * @return array<string, mixed>
     */
    public function getObligationDetail(ObligationInstance $obligation, User $viewer): array
    {
        $this->authorizeObligationAccess($obligation, $viewer);

        $obligation->loadMissing([
            'definition.authority',
            'definition.source',
            'definition.rulePack',
            'assessmentResult.ruleVersion.source',
            'assignedTo',
            'lastReviewedBy',
            'documents.uploader',
            'documents.verifiedBy',
            'activityLogs.user',
            'activityLogs.fromUser',
            'activityLogs.toUser',
        ]);

        $summary = $this->formatObligationSummary($obligation);
        $transitions = $this->stateMachine->getAvailableTransitions($obligation, $viewer);

        // Format linked documents with rich metadata
        $now = Carbon::now()->toDateString();
        $formattedDocs = $obligation->documents->map(function (ComplianceDocument $doc) use ($now) {
            $isExpired = ($doc->verification_status === 'EXPIRED') 
                || ($doc->expiry_date && $doc->expiry_date->toDateString() < $now);

            return [
                'id' => $doc->id,
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

        // Format activity timeline
        $timeline = $obligation->activityLogs->map(function (ObligationActivityLog $log) {
            return [
                'id' => $log->id,
                'activity_type' => $log->activity_type,
                'from_status' => $log->from_status,
                'to_status' => $log->to_status,
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
            'obligation' => $summary,
            'documents' => $formattedDocs,
            'available_transitions' => $transitions,
            'timeline' => $timeline,
        ];
    }

    /**
     * Assign or reassign an obligation instance to a user.
     *
     * @throws AuthorizationException
     * @throws ConcurrentUpdateException
     */
    public function assignObligation(
        ObligationInstance $obligation,
        ?User $assignee,
        User $actor,
        ?int $lockVersion = null,
        ?string $notes = null
    ): ObligationInstance {
        $this->authorizeObligationAdmin($obligation, $actor);

        // If assigning to a user, verify assignee is an active member of THIS organisation
        if ($assignee !== null) {
            $assigneeMembership = Membership::where('organisation_id', $obligation->organisation_id)
                ->where('user_id', $assignee->id)
                ->where('status', 'ACTIVE')
                ->first();

            if (!$assigneeMembership) {
                throw new AuthorizationException(
                    "Target user [{$assignee->name}] is not an active member of this organisation. Cross-organisation assignments are strictly prohibited."
                );
            }
        }

        return DB::transaction(function () use ($obligation, $assignee, $actor, $lockVersion, $notes) {
            $locked = ObligationInstance::where('id', $obligation->id)->lockForUpdate()->firstOrFail();

            // Optimistic concurrency protection
            if ($lockVersion !== null && $locked->lock_version !== $lockVersion) {
                throw new ConcurrentUpdateException(
                    'Conflict detected: This obligation has been modified by another user. Please refresh and try again.'
                );
            }

            $previousUserId = $locked->assigned_to_user_id;
            $newUserId = $assignee?->id;

            $locked->update([
                'assigned_to_user_id' => $newUserId,
                'lock_version' => $locked->lock_version + 1,
            ]);

            // Record in dedicated activity log
            ObligationActivityLog::create([
                'tenant_id' => $locked->tenant_id,
                'organisation_id' => $locked->organisation_id,
                'obligation_instance_id' => $locked->id,
                'user_id' => $actor->id,
                'activity_type' => 'ASSIGNMENT_CHANGED',
                'from_user_id' => $previousUserId,
                'to_user_id' => $newUserId,
                'notes' => $notes ?? ($newUserId ? "Assigned to {$assignee->name}." : "Unassigned responsibility."),
                'created_at' => Carbon::now(),
            ]);

            // Record in enterprise AuditEvent
            AuditEvent::create([
                'tenant_id' => $locked->tenant_id,
                'user_id' => $actor->id,
                'event_type' => 'OBLIGATION_ASSIGNMENT_CHANGED',
                'auditable_type' => ObligationInstance::class,
                'auditable_id' => $locked->id,
                'payload' => [
                    'previous_user_id' => $previousUserId,
                    'new_user_id' => $newUserId,
                    'obligation_definition' => $locked->definition?->code,
                ],
            ]);

            return $locked->fresh(['assignedTo', 'activityLogs.user']);
        });
    }

    /**
     * Transition an obligation's lifecycle status following state machine rules.
     *
     * @throws AuthorizationException
     * @throws InvalidLifecycleTransitionException
     * @throws ConcurrentUpdateException
     */
    public function transitionStatus(
        ObligationInstance $obligation,
        string $newStatus,
        User $actor,
        ?int $lockVersion = null,
        ?string $notes = null
    ): ObligationInstance {
        $this->authorizeObligationAccess($obligation, $actor);

        // Validate lifecycle transition rules and permissions
        $this->stateMachine->validateTransition($obligation, $newStatus, $actor, $notes);

        return DB::transaction(function () use ($obligation, $newStatus, $actor, $lockVersion, $notes) {
            $locked = ObligationInstance::where('id', $obligation->id)->lockForUpdate()->firstOrFail();

            // Optimistic concurrency protection
            if ($lockVersion !== null && $locked->lock_version !== $lockVersion) {
                throw new ConcurrentUpdateException(
                    'Conflict detected: This obligation has been modified by another user. Please refresh and try again.'
                );
            }

            $fromStatus = $this->stateMachine->normalizeStatus($locked->status);
            $normalizedTarget = $this->stateMachine->normalizeStatus($newStatus);

            $updates = [
                'status' => $normalizedTarget,
                'lock_version' => $locked->lock_version + 1,
            ];

            // If moving to review or completion, record reviewer
            if (in_array($normalizedTarget, [
                ComplianceLifecycleStateMachine::UNDER_REVIEW,
                ComplianceLifecycleStateMachine::COMPLETED_INTERNAL,
                ComplianceLifecycleStateMachine::VERIFIED_COMPLETE,
            ], true)) {
                $updates['last_reviewed_at'] = Carbon::now();
                $updates['last_reviewed_by_user_id'] = $actor->id;
            }

            if (!empty($notes)) {
                $updates['internal_notes'] = $notes;
            }

            $locked->update($updates);

            // Record in dedicated activity log
            ObligationActivityLog::create([
                'tenant_id' => $locked->tenant_id,
                'organisation_id' => $locked->organisation_id,
                'obligation_instance_id' => $locked->id,
                'user_id' => $actor->id,
                'activity_type' => 'STATUS_TRANSITION',
                'from_status' => $fromStatus,
                'to_status' => $normalizedTarget,
                'notes' => $notes,
                'created_at' => Carbon::now(),
            ]);

            // Record in enterprise AuditEvent
            AuditEvent::create([
                'tenant_id' => $locked->tenant_id,
                'user_id' => $actor->id,
                'event_type' => 'OBLIGATION_STATUS_TRANSITION',
                'auditable_type' => ObligationInstance::class,
                'auditable_id' => $locked->id,
                'payload' => [
                    'from_status' => $fromStatus,
                    'to_status' => $normalizedTarget,
                    'actor_id' => $actor->id,
                    'notes' => $notes,
                ],
            ]);

            return $locked->fresh(['assignedTo', 'lastReviewedBy', 'activityLogs.user']);
        });
    }

    /**
     * Update due date or compliance frequency.
     */
    public function updateSchedule(
        ObligationInstance $obligation,
        ?string $dueDate,
        ?string $frequency,
        User $actor,
        ?int $lockVersion = null,
        ?string $notes = null
    ): ObligationInstance {
        $this->authorizeObligationAdmin($obligation, $actor);

        return DB::transaction(function () use ($obligation, $dueDate, $frequency, $actor, $lockVersion, $notes) {
            $locked = ObligationInstance::where('id', $obligation->id)->lockForUpdate()->firstOrFail();

            if ($lockVersion !== null && $locked->lock_version !== $lockVersion) {
                throw new ConcurrentUpdateException(
                    'Conflict detected: This obligation has been modified by another user. Please refresh and try again.'
                );
            }

            $updates = [
                'lock_version' => $locked->lock_version + 1,
            ];

            if ($dueDate !== null) {
                $updates['next_due_date'] = $dueDate;
            }

            if ($frequency !== null) {
                $updates['compliance_frequency'] = $frequency;
            }

            if ($notes !== null) {
                $updates['internal_notes'] = $notes;
            }

            $locked->update($updates);

            ObligationActivityLog::create([
                'tenant_id' => $locked->tenant_id,
                'organisation_id' => $locked->organisation_id,
                'obligation_instance_id' => $locked->id,
                'user_id' => $actor->id,
                'activity_type' => 'DUE_DATE_UPDATED',
                'notes' => $notes ?? "Updated compliance schedule (Due: {$dueDate}, Freq: {$frequency}).",
                'created_at' => Carbon::now(),
            ]);

            return $locked->fresh();
        });
    }

    /**
     * Authorize that the user belongs to the same tenant and organisation as the obligation.
     */
    public function authorizeObligationAccess(ObligationInstance $obligation, User $user): void
    {
        $membership = Membership::where('organisation_id', $obligation->organisation_id)
            ->where('user_id', $user->id)
            ->where('status', 'ACTIVE')
            ->first();

        if (!$membership) {
            throw new AuthorizationException('Unauthorized access: You do not have active membership in the responsible organisation.');
        }
    }

    /**
     * Authorize that the user is an administrator or owner of the obligation's organisation.
     */
    public function authorizeObligationAdmin(ObligationInstance $obligation, User $user): void
    {
        $membership = Membership::where('organisation_id', $obligation->organisation_id)
            ->where('user_id', $user->id)
            ->where('status', 'ACTIVE')
            ->first();

        if (!$membership || !$membership->isOwnerOrAdmin()) {
            throw new AuthorizationException('Administrative privileges required to modify organisation assignments or regulatory configurations.');
        }
    }

    /**
     * Determine next actionable step for display.
     */
    protected function determineOutstandingAction(
        ObligationInstance $instance,
        string $status,
        int $linkedDocsCount,
        int $verifiedDocsCount
    ): string {
        return match ($status) {
            ComplianceLifecycleStateMachine::NOT_STARTED => 'Assign an internal owner and initiate compliance task preparation.',
            ComplianceLifecycleStateMachine::IN_PROGRESS => $linkedDocsCount === 0
                ? 'Prepare calculations/returns and upload required statutory evidence.'
                : 'Complete internal preparation and move to internal review.',
            ComplianceLifecycleStateMachine::AWAITING_EVIDENCE => 'Upload and link required statutory filing receipts or tax clearance certificates.',
            ComplianceLifecycleStateMachine::UNDER_REVIEW => 'Compliance officer must verify supporting evidence and confirm regulatory accuracy.',
            ComplianceLifecycleStateMachine::COMPLETED_INTERNAL => 'Internal task completed. Awaiting official government verification / receipt to certify statutory compliance.',
            ComplianceLifecycleStateMachine::VERIFIED_COMPLETE => 'Requirement verified with official documentation.',
            ComplianceLifecycleStateMachine::BLOCKED => 'Resolve documented external blocking condition or pending dependencies.',
            ComplianceLifecycleStateMachine::NOT_APPLICABLE => 'Confirmed exempt or non-applicable under evaluated statutory parameters.',
            default => 'Review current status and take required compliance actions.',
        };
    }
}
