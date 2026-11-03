<?php

namespace App\Domains\RegistrationEngine\Models;

use App\Domains\AuthAndTenant\Models\Organisation;
use App\Models\User;
use App\Shared\Traits\HasTenant;
use App\Shared\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ObligationInstance extends Model
{
    use HasTenant, HasUuid;

    protected $table = 'obligation_instances';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'declared_registered_at' => 'date',
            'next_due_date' => 'date',
            'last_reviewed_at' => 'datetime',
            'lock_version' => 'integer',
        ];
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'organisation_id');
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(ObligationDefinition::class, 'definition_id');
    }

    public function assessmentResult(): BelongsTo
    {
        return $this->belongsTo(AssessmentResult::class, 'assessment_result_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function lastReviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_reviewed_by_user_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ComplianceDocument::class, 'obligation_instance_id');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ObligationActivityLog::class, 'obligation_instance_id')->orderBy('created_at', 'desc');
    }

    public function filingOccurrences(): HasMany
    {
        return $this->hasMany(\App\Domains\ComplianceEngine\Models\FilingOccurrence::class, 'obligation_instance_id')
            ->orderBy('filing_period_start', 'asc');
    }

    public function isCompliant(): bool
    {
        return in_array($this->status, ['COMPLIANT', 'VERIFIED_COMPLETE'], true);
    }

    public function isVerifiedComplete(): bool
    {
        return $this->status === 'VERIFIED_COMPLETE';
    }

    public function isCompletedInternal(): bool
    {
        return $this->status === 'COMPLETED_INTERNAL';
    }

    public function isUnderReview(): bool
    {
        return $this->status === 'UNDER_REVIEW';
    }

    public function isAwaitingEvidence(): bool
    {
        return $this->status === 'AWAITING_EVIDENCE';
    }

    public function isInProgress(): bool
    {
        return $this->status === 'IN_PROGRESS';
    }

    public function isBlocked(): bool
    {
        return $this->status === 'BLOCKED';
    }

    public function isNotApplicable(): bool
    {
        return in_array($this->status, ['NOT_APPLICABLE', 'EXEMPT'], true);
    }

    /**
     * User declared without verified supporting document.
     * Note (Guideline 4): Does NOT automatically confer verified compliance.
     */
    public function isDeclaredOnly(): bool
    {
        return $this->evidence_status === 'USER_DECLARED';
    }

    public function isVerifiedByIssuer(): bool
    {
        return $this->evidence_status === 'VERIFIED_BY_ISSUER';
    }
}

