<?php

namespace App\Shared\Traits;

use App\Shared\Scopes\TenantScope;
use App\Shared\Services\TenantContext;

trait HasTenant
{
    use HasUuid;

    protected static function bootHasTenant(): void
    {
        static::addGlobalScope(new TenantScope());

        static::creating(function ($model) {
            $tenantContext = app(TenantContext::class);

            if (empty($model->tenant_id) && $tenantContext->hasTenant()) {
                $model->tenant_id = $tenantContext->getTenantId();
            }
        });
    }

    public function tenant()
    {
        return $this->belongsTo(\App\Domains\AuthAndTenant\Models\Tenant::class);
    }
}
