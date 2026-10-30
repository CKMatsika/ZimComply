<?php

namespace App\Http\Middleware;

use App\Domains\AuthAndTenant\Models\Membership;
use App\Domains\AuthAndTenant\Models\Organisation;
use App\Domains\AuthAndTenant\Models\Tenant;
use App\Shared\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceTenantContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenantContext = app(TenantContext::class);

        if ($user = $request->user()) {
            $sessionTenantId = session('active_tenant_id');
            $sessionOrgId = session('active_organisation_id');

            // Find valid membership
            $membershipQuery = Membership::withoutGlobalScopes()
                ->where('user_id', $user->id)
                ->where('status', 'ACTIVE');

            if ($sessionTenantId) {
                $membershipQuery->where('tenant_id', $sessionTenantId);
            }

            $membership = $membershipQuery->first();

            // Fallback to any active membership
            if (!$membership) {
                $membership = Membership::withoutGlobalScopes()
                    ->where('user_id', $user->id)
                    ->where('status', 'ACTIVE')
                    ->first();
            }

            if ($membership) {
                $tenant = Tenant::find($membership->tenant_id);
                $tenantContext->setTenant($tenant);
                session(['active_tenant_id' => $tenant?->id]);

                // Resolve active organisation
                $organisation = null;
                if ($sessionOrgId) {
                    $organisation = Organisation::where('tenant_id', $tenant?->id)->find($sessionOrgId);
                }

                if (!$organisation) {
                    $organisation = Organisation::where('tenant_id', $tenant?->id)->first();
                }

                $tenantContext->setOrganisation($organisation);
                session(['active_organisation_id' => $organisation?->id]);
            }
        }

        return $next($request);
    }
}
