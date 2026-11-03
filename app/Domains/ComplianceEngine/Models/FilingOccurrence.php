<?php

namespace App\Domains\ComplianceEngine\Models;

use App\Domains\AuthAndTenant\Models\Organisation;
use App\Domains\RegistrationEngine\Models\ComplianceDocument;
use App\Domains\RegistrationEngine\Models\ObligationDefinition;
use App\Domains\RegistrationEngine\Models\ObligationInstance;
use App\Domains\RegistrationEngine\Models\RuleVersion;
use App\Models\User;
use App\Shared\Traits\HasTenant;
use App\Shared\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FilingOccurrence extends Model
{
    use HasTenant, HasUuid;

    protected $table = 'filing_occurrences';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'filing_period_start' => 'date',
            'filing_period_end' => 'date',
            'original_statutory_due_date' => 'date',
            'current_due_date' => 'date',
            'payment_due_date' => 'date',
            'deadline_calculation_basis' => 'array',
            'internally_approved_at' => 'datetime',
            'submitted_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'closed_at' => 'datetime',
            'is_obligation_currently_applicable' => 'boolean',
            'lock_version' => 'integer',
        ];
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'organisation_id');
    }

    public function obligationInstance(): BelongsTo
    {
        return $this->belongsTo(ObligationInstance::class, 'obligation_instance_id');
    }

    public function obligationDefinition(): BelongsTo
    {
        return $this->belongsTo(ObligationDefinition::class, 'obligation_definition_id');
    }

    public function ruleVersion(): BelongsTo
    {
        return $this->belongsTo(RuleVersion::class, 'rule_version_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function internallyApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'internally_approved_by_user_id');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by_user_id');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ComplianceDocument::class, 'filing_occurrence_id');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(FilingOccurrenceActivityLog::class, 'filing_occurrence_id')
            ->orderBy('created_at', 'desc');
    }

    public function isCompleted(): bool
    {
        return in_array($this->workflow_status, ['SUBMITTED', 'ACKNOWLEDGED', 'CLOSED'], true);
    }

    public function isOfficiallyAcknowledged(): bool
    {
        return $this->official_submission_status === 'OFFICIALLY_ACKNOWLEDGED'
            || $this->workflow_status === 'ACKNOWLEDGED';
    }

    public function isDeadlineUnverified(): bool
    {
        return in_array($this->verification_status, ['DEADLINE_UNVERIFIED', 'UNVERIFIED_HOLD'], true)
            || $this->current_due_date === null;
    }
}

