<?php

namespace App\Shared\Services;

use App\Domains\AuthAndTenant\Models\Tenant;
use App\Domains\AuthAndTenant\Models\Organisation;

class TenantContext
{
    protected ?Tenant $tenant = null;
    protected ?Organisation $organisation = null;

    public function setTenant(?Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function getTenant(): ?Tenant
    {
        return $this->tenant;
    }

    public function getTenantId(): ?string
    {
        return $this->tenant?->id;
    }

    public function setOrganisation(?Organisation $organisation): void
    {
        $this->organisation = $organisation;
    }

    public function getOrganisation(): ?Organisation
    {
        return $this->organisation;
    }

    public function getOrganisationId(): ?string
    {
        return $this->organisation?->id;
    }

    public function hasTenant(): bool
    {
        return $this->tenant !== null;
    }
}
