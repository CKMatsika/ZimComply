<?php

namespace App\Domains\RegistrationEngine\Models;

use App\Shared\Traits\HasUuid;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RulePack extends Model
{
    use HasUuid;

    protected $table = 'rule_packs';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function authority(): BelongsTo
    {
        return $this->belongsTo(RegulatoryAuthority::class, 'authority_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(RuleVersion::class, 'rule_pack_id');
    }

    public function activeVersion(?Carbon $asOfDate = null): HasOne
    {
        $date = ($asOfDate ?? Carbon::now())->toDateString();

        return $this->hasOne(RuleVersion::class, 'rule_pack_id')
            ->where('status', 'ACTIVE')
            ->where('verification_status', 'VERIFIED_OFFICIAL')
            ->where('effective_from', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date);
            });
    }

    public function obligationDefinitions(): HasMany
    {
        return $this->hasMany(ObligationDefinition::class, 'rule_pack_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
