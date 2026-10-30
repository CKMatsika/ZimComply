<?php

namespace App\Domains\RegistrationEngine\Models;

use App\Shared\Traits\HasTenant;
use App\Shared\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentResult extends Model
{
    use HasTenant, HasUuid;

    protected $table = 'assessment_results';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'trigger_reasons' => 'array',
            'prerequisites_snapshot' => 'array',
            'required_documents_snapshot' => 'array',
            'evaluated_at' => 'datetime',
        ];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(ApplicabilityAssessment::class, 'assessment_id');
    }

    public function obligationDefinition(): BelongsTo
    {
        return $this->belongsTo(ObligationDefinition::class, 'obligation_definition_id');
    }

    public function ruleVersion(): BelongsTo
    {
        return $this->belongsTo(RuleVersion::class, 'rule_version_id');
    }

    public function isApplicable(): bool
    {
        return $this->decision === 'APPLICABLE';
    }

    public function isExempt(): bool
    {
        return $this->decision === 'EXEMPT';
    }
}
