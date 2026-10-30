<?php

namespace App\Domains\AuthAndTenant\Models;

use App\Shared\Traits\HasTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organisation extends Model
{
    use HasTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'legal_name',
        'trading_name',
        'entity_type',
        'tin',
        'incorporation_number',
        'incorporation_date',
        'base_currency',
        'fiscal_year_end_month',
        'primary_email',
        'primary_phone',
        'registered_office_address',
    ];

    protected $casts = [
        'tin' => 'encrypted',
        'incorporation_date' => 'date',
        'fiscal_year_end_month' => 'integer',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }
}
