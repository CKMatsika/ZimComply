<?php

namespace App\Domains\AuthAndTenant\Models;

use App\Shared\Traits\HasTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    use HasTenant;

    protected $fillable = [
        'tenant_id',
        'plan_code',
        'status',
        'entitlements',
        'starts_at',
        'expires_at',
    ];

    protected $casts = [
        'entitlements' => 'array',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function isActive(): bool
    {
        return in_array($this->status, ['TRIAL', 'ACTIVE']) &&
            ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function canAccessFeature(string $feature): bool
    {
        if (!$this->isActive()) {
            return false;
        }

        return !empty($this->entitlements[$feature]);
    }
}
