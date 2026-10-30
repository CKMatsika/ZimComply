<?php

namespace App\Domains\RegistrationEngine\Models;

use App\Shared\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ObligationDefinition extends Model
{
    use HasUuid;

    protected $table = 'obligation_definitions';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'applicability_criteria' => 'array',
            'prerequisites' => 'array',
            'required_documents' => 'array',
            'estimated_processing_days' => 'integer',
            'is_entity_level' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function authority(): BelongsTo
    {
        return $this->belongsTo(RegulatoryAuthority::class, 'authority_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(RegulatorySource::class, 'source_id');
    }

    public function rulePack(): BelongsTo
    {
        return $this->belongsTo(RulePack::class, 'rule_pack_id');
    }

    public function instances(): HasMany
    {
        return $this->hasMany(ObligationInstance::class, 'definition_id');
    }

    public function isVerified(): bool
    {
        return $this->verification_status === 'VERIFIED_OFFICIAL';
    }

    public function isMandatoryStatutory(): bool
    {
        return $this->legal_classification === 'MANDATORY_STATUTORY';
    }

    public function isConditionalStatutory(): bool
    {
        return $this->legal_classification === 'CONDITIONAL_STATUTORY';
    }

    public function isCollectiveBargaining(): bool
    {
        return $this->legal_classification === 'CBA_COLLECTIVE_AGREEMENT';
    }

    public function isProfessionalRegulation(): bool
    {
        return $this->legal_classification === 'PROFESSIONAL_REGULATION';
    }

    public function isTenderSpecific(): bool
    {
        return $this->legal_classification === 'TENDER_SPECIFIC';
    }

    public function isOptionalAssociation(): bool
    {
        return $this->legal_classification === 'OPTIONAL_ASSOCIATION';
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeVerified(Builder $query): Builder
    {
        return $query->where('verification_status', 'VERIFIED_OFFICIAL');
    }

    public function scopeClassification(Builder $query, string $classification): Builder
    {
        return $query->where('legal_classification', $classification);
    }
}
