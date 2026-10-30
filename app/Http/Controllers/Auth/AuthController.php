<?php

namespace App\Http\Controllers\Auth;

use App\Domains\AuthAndTenant\Models\AuditEvent;
use App\Domains\AuthAndTenant\Models\Branch;
use App\Domains\AuthAndTenant\Models\Membership;
use App\Domains\AuthAndTenant\Models\Organisation;
use App\Domains\AuthAndTenant\Models\Subscription;
use App\Domains\AuthAndTenant\Models\Tenant;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class AuthController extends Controller
{
    public function showLogin(): Response
    {
        return Inertia::render('Auth/Login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            $user = Auth::user();
            $membership = Membership::withoutGlobalScopes()
                ->where('user_id', $user->id)
                ->where('status', 'ACTIVE')
                ->first();

            if ($membership) {
                session(['active_tenant_id' => $membership->tenant_id]);
                $org = Organisation::where('tenant_id', $membership->tenant_id)->first();
                if ($org) {
                    session(['active_organisation_id' => $org->id]);
                }

                // Record audit log
                AuditEvent::create([
                    'tenant_id' => $membership->tenant_id,
                    'user_id' => $user->id,
                    'event_type' => 'AUTH_LOGIN_SUCCESS',
                    'auditable_type' => User::class,
                    'auditable_id' => $user->id,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);
            }

            return redirect()->intended(route('dashboard'));
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our Zimbabwean business registry records.',
        ])->onlyInput('email');
    }

    public function showRegister(): Response
    {
        return Inertia::render('Auth/Register');
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'company_name' => ['required', 'string', 'max:255'],
            'entity_type' => ['required', 'string', 'in:PRIVATE_LIMITED_COMPANY,PBC,PARTNERSHIP,SOLE_PROPRIETORSHIP,TRUST'],
            'account_type' => ['required', 'string', 'in:SME,ENTERPRISE,ACCOUNTING_FIRM'],
        ]);

        $result = DB::transaction(function () use ($data, $request) {
            // 1. Create User
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => Hash::make($data['password']),
            ]);

            // 2. Create Tenant
            $tenant = Tenant::create([
                'name' => $data['company_name'],
                'type' => $data['account_type'],
                'status' => 'TRIAL',
                'trial_ends_at' => now()->addDays(30),
            ]);

            // 3. Create Primary Organisation
            $org = Organisation::create([
                'tenant_id' => $tenant->id,
                'legal_name' => $data['company_name'],
                'trading_name' => $data['company_name'],
                'entity_type' => $data['entity_type'],
                'base_currency' => 'USD',
                'primary_email' => $data['email'],
                'primary_phone' => $data['phone'] ?? null,
            ]);

            // 4. Create Headquarters Branch
            Branch::create([
                'tenant_id' => $tenant->id,
                'organisation_id' => $org->id,
                'name' => 'Headquarters (Harare)',
                'code' => 'HQ-01',
                'city' => 'Harare',
                'physical_address' => 'Harare Central Business District, Zimbabwe',
                'is_headquarters' => true,
            ]);

            // 5. Create Owner Membership
            Membership::create([
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'organisation_id' => $org->id,
                'role' => 'OWNER',
                'status' => 'ACTIVE',
            ]);

            // 6. Create Starter Subscription
            Subscription::create([
                'tenant_id' => $tenant->id,
                'plan_code' => $data['account_type'] === 'ACCOUNTING_FIRM' ? 'ACCOUNTING_PRO' : 'REGISTRATION_STARTER',
                'status' => 'TRIAL',
                'entitlements' => [
                    'max_companies' => $data['account_type'] === 'ACCOUNTING_FIRM' ? 25 : 1,
                    'registration_roadmap' => true,
                    'compliance_wallet' => true,
                    'praz_matching' => true,
                ],
                'starts_at' => now(),
                'expires_at' => now()->addDays(30),
            ]);

            // 7. Audit log
            AuditEvent::create([
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'event_type' => 'TENANT_INITIALIZED',
                'auditable_type' => Tenant::class,
                'auditable_id' => $tenant->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return ['user' => $user, 'tenant' => $tenant, 'org' => $org];
        });

        Auth::login($result['user']);
        session([
            'active_tenant_id' => $result['tenant']->id,
            'active_organisation_id' => $result['org']->id,
        ]);

        return redirect()->route('dashboard')->with('success', 'Welcome to ZimComply AI! Your company account has been initialized.');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function switchOrganisation(Request $request)
    {
        $request->validate([
            'organisation_id' => ['required', 'string', 'uuid'],
        ]);

        $tenantId = session('active_tenant_id');
        $org = Organisation::where('tenant_id', $tenantId)->findOrFail($request->organisation_id);

        session(['active_organisation_id' => $org->id]);

        return back()->with('success', "Switched active workspace to {$org->legal_name}");
    }
}
