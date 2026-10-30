<?php

namespace Tests\Feature;

use App\Domains\AuthAndTenant\Models\Branch;
use App\Domains\AuthAndTenant\Models\Organisation;
use App\Domains\AuthAndTenant\Models\Tenant;
use App\Shared\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_scope_prevents_cross_tenant_data_access(): void
    {
        // 1. Create Tenant A & Organisation A
        $tenantA = Tenant::create([
            'name' => 'Tenant Alpha',
            'type' => 'SME',
            'status' => 'ACTIVE',
        ]);
        $orgA = Organisation::create([
            'tenant_id' => $tenantA->id,
            'legal_name' => 'Alpha Industrial (Pvt) Ltd',
            'base_currency' => 'USD',
        ]);

        // 2. Create Tenant B & Organisation B
        $tenantB = Tenant::create([
            'name' => 'Tenant Beta',
            'type' => 'SME',
            'status' => 'ACTIVE',
        ]);
        $orgB = Organisation::create([
            'tenant_id' => $tenantB->id,
            'legal_name' => 'Beta Mining Supplies Ltd',
            'base_currency' => 'USD',
        ]);

        // 3. Set TenantContext to Tenant A
        $tenantContext = app(TenantContext::class);
        $tenantContext->setTenant($tenantA);

        // 4. Query organisations under Tenant A context
        $organisations = Organisation::all();

        // 5. Assert Tenant A can only see Organisation A, never Organisation B
        $this->assertTrue($organisations->contains('id', $orgA->id));
        $this->assertFalse($organisations->contains('id', $orgB->id));
        $this->assertEquals(1, $organisations->count());

        // 6. Switch context to Tenant B
        $tenantContext->setTenant($tenantB);
        $organisationsB = Organisation::all();

        // 7. Assert Tenant B can only see Organisation B, never Organisation A
        $this->assertTrue($organisationsB->contains('id', $orgB->id));
        $this->assertFalse($organisationsB->contains('id', $orgA->id));
        $this->assertEquals(1, $organisationsB->count());
    }

    public function test_branch_creation_automatically_injects_active_tenant_id(): void
    {
        $tenant = Tenant::create([
            'name' => 'Harare Holdings',
            'type' => 'SME',
            'status' => 'ACTIVE',
        ]);
        $org = Organisation::create([
            'tenant_id' => $tenant->id,
            'legal_name' => 'Harare Holdings Pvt Ltd',
        ]);

        $tenantContext = app(TenantContext::class);
        $tenantContext->setTenant($tenant);

        // Create branch without explicitly providing tenant_id
        $branch = Branch::create([
            'organisation_id' => $org->id,
            'name' => 'Chitungwiza Branch',
            'physical_address' => 'Unit L, Seke, Chitungwiza',
        ]);

        $this->assertEquals($tenant->id, $branch->tenant_id);
    }
}
