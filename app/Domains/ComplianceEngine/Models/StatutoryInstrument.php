<?php

namespace App\Domains\ComplianceEngine\Models;

use App\Domains\AuthAndTenant\Models\Organisation;
use App\Domains\RegistrationEngine\Models\ComplianceDocument;
use App\Domains\RegistrationEngine\Models\ObligationDefinition;
use App\Domains\RegistrationEngine\Models\ObligationInstance;
use App\Domains\RegistrationEngine\Models\RegulatoryAuthority;
use App\Domains\RegistrationEngine\Models\RuleVersion;
use App\Models\User;
use App\Shared\Traits\HasTenant;
use App\Shared\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class StatutoryInstrument extends Model
{
    use HasTenant, HasUuid;

    protected $table = 'statutory_instruments';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'expiry_date' => 'date',
            'renewal_window_opens_at' => 'date',
            'statutory_renewal_deadline' => 'date',
            'deadline_calculation_basis' => 'array',
            'metadata_version' => 'integer',
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

    public function authority(): BelongsTo
    {
        return $this->belongsTo(RegulatoryAuthority::class, 'regulatory_authority_id');
    }

    public function ruleVersion(): BelongsTo
    {
        return $this->belongsTo(RuleVersion::class, 'rule_version_id');
    }

    public function currentDocument(): BelongsTo
    {
        return $this->belongsTo(ComplianceDocument::class, 'current_document_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function renewalAttempts(): HasMany
    {
        return $this->hasMany(RenewalAttempt::class, 'statutory_instrument_id')
            ->orderByDesc('attempt_number');
    }

    public function activeRenewalAttempt(): HasOne
    {
        return $this->hasOne(RenewalAttempt::class, 'statutory_instrument_id')
            ->where('is_active_attempt', true);
    }

    public function evidenceLinks(): HasMany
    {
        return $this->hasMany(RenewalEvidenceLink::class, 'statutory_instrument_id')
            ->orderByDesc('created_at');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(RenewalActivityLog::class, 'statutory_instrument_id')
            ->orderByDesc('created_at');
    }
}

