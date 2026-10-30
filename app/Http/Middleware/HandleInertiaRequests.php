<?php

namespace App\Http\Middleware;

use App\Domains\AuthAndTenant\Models\Membership;
use App\Domains\AuthAndTenant\Models\Organisation;
use App\Shared\Services\TenantContext;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $tenantContext = app(TenantContext::class);
        $user = $request->user();

        $activeTenant = $tenantContext->getTenant();
        $activeOrg = $tenantContext->getOrganisation();

        $availableOrganisations = [];
        $currentRole = null;

        if ($user && $activeTenant) {
            $membership = Membership::withoutGlobalScopes()
                ->where('tenant_id', $activeTenant->id)
                ->where('user_id', $user->id)
                ->first();

            $currentRole = $membership?->role;

            $availableOrganisations = Organisation::where('tenant_id', $activeTenant->id)
                ->select(['id', 'legal_name', 'trading_name', 'entity_type', 'base_currency'])
                ->get();
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'has_mfa' => $user->hasMfaEnabled(),
                ] : null,
                'active_tenant' => $activeTenant ? [
                    'id' => $activeTenant->id,
                    'name' => $activeTenant->name,
                    'type' => $activeTenant->type,
                    'status' => $activeTenant->status,
                ] : null,
                'active_organisation' => $activeOrg ? [
                    'id' => $activeOrg->id,
                    'legal_name' => $activeOrg->legal_name,
                    'trading_name' => $activeOrg->trading_name,
                    'entity_type' => $activeOrg->entity_type,
                    'base_currency' => $activeOrg->base_currency,
                ] : null,
                'available_organisations' => $availableOrganisations,
                'role' => $currentRole,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'info' => fn () => $request->session()->get('info'),
            ],
        ];
    }
}
