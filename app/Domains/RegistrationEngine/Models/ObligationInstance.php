<?php

namespace App\Domains\RegistrationEngine\Models;

use App\Domains\AuthAndTenant\Models\Organisation;
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

    public function documents(): HasMany
    {
        return $this->hasMany(ComplianceDocument::class, 'obligation_instance_id');
    }

    public function isCompliant(): bool
    {
        return $this->status === 'COMPLIANT';
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
