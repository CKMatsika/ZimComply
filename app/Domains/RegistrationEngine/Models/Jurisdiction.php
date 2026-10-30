<?php

namespace App\Domains\RegistrationEngine\Models;

use App\Shared\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Jurisdiction extends Model
{
    use HasUuid;

    protected $table = 'jurisdictions';

    protected $guarded = [];

    public function scopeProvince(Builder $query, string $province): Builder
    {
        return $query->where('province', $province);
    }

    public function scopeCouncilType(Builder $query, string $councilType): Builder
    {
        return $query->where('council_type', $councilType);
    }
}
