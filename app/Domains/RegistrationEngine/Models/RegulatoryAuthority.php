<?php

namespace App\Domains\RegistrationEngine\Models;

use App\Shared\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RegulatoryAuthority extends Model
{
    use HasUuid;

    protected $table = 'regulatory_authorities';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'official_contact' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function sources(): HasMany
    {
        return $this->hasMany(RegulatorySource::class, 'authority_id');
    }

    public function rulePacks(): HasMany
    {
        return $this->hasMany(RulePack::class, 'authority_id');
    }

    public function obligationDefinitions(): HasMany
    {
        return $this->hasMany(ObligationDefinition::class, 'authority_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }
}
