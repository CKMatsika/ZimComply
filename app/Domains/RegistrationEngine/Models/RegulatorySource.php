<?php

namespace App\Domains\RegistrationEngine\Models;

use App\Shared\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RegulatorySource extends Model
{
    use HasUuid;

    protected $table = 'regulatory_sources';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'gazette_date' => 'date',
            'verified_at' => 'datetime',
        ];
    }

    public function authority(): BelongsTo
    {
        return $this->belongsTo(RegulatoryAuthority::class, 'authority_id');
    }

    public function ruleVersions(): HasMany
    {
        return $this->hasMany(RuleVersion::class, 'source_id');
    }

    public function obligationDefinitions(): HasMany
    {
        return $this->hasMany(ObligationDefinition::class, 'source_id');
    }

    public function isVerified(): bool
    {
        return $this->verification_status === 'VERIFIED_OFFICIAL';
    }

    public function scopeVerified(Builder $query): Builder
    {
        return $query->where('verification_status', 'VERIFIED_OFFICIAL');
    }
}
