<?php

namespace App\Domains\AuthAndTenant\Models;

use App\Shared\Traits\HasTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Branch extends Model
{
    use HasTenant;

    protected $fillable = [
        'tenant_id',
        'organisation_id',
        'name',
        'code',
        'city',
        'physical_address',
        'is_headquarters',
    ];

    protected $casts = [
        'is_headquarters' => 'boolean',
    ];

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }
}
