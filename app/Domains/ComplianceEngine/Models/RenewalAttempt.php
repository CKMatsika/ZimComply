<?php

namespace App\Domains\ComplianceEngine\Models;

use App\Domains\AuthAndTenant\Models\Organisation;
use App\Domains\RegistrationEngine\Models\ComplianceDocument;
use App\Domains\RegistrationEngine\Models\ObligationInstance;
use App\Domains\RegistrationEngine\Models\RuleVersion;
use App\Models\User;
use App\Shared\Traits\HasTenant;
use App\Shared\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RenewalAttempt extends Model
{
    use HasTenant, HasUuid;

    protected $table = 'renewal_attempts';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'attempt_number' => 'integer',
            'is_active_attempt' => 'boolean',
            'prior_issue_date' => 'date',
            'prior_expiry_date' => 'date',
            'replacement_issue_date' => 'date',
            'replacement_expiry_date' => 'date',
            'statutory_renewal_deadline' => 'date',
            'deadline_calculation_basis' => 'array',
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
            'decision_recorded_at' => 'datetime',
            'lock_version' => 'integer',
        ];
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'organisation_id');
    }

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(StatutoryInstrument::class, 'statutory_instrument_id');
    }

    public function obligationInstance(): BelongsTo
    {
        return $this->belongsTo(ObligationInstance::class, 'obligation_instance_id');
    }

    public function ruleVersion(): BelongsTo
    {
        return $this->belongsTo(RuleVersion::class, 'rule_version_id');
    }

    public function priorDocument(): BelongsTo
    {
        return $this->belongsTo(ComplianceDocument::class, 'prior_document_id');
    }

    public function replacementDocument(): BelongsTo
    {
        return $this->belongsTo(ComplianceDocument::class, 'replacement_document_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function startedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by_user_id');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    public function decisionRecordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decision_recorded_by_user_id');
    }

    public function evidenceLinks(): HasMany
    {
        return $this->hasMany(RenewalEvidenceLink::class, 'renewal_attempt_id')
            ->orderByDesc('created_at');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(RenewalActivityLog::class, 'renewal_attempt_id')
            ->orderByDesc('created_at');
    }
}

