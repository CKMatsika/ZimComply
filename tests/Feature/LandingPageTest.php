<?php

namespace Tests\Feature;

use App\Domains\AuthAndTenant\Models\Membership;
use App\Domains\AuthAndTenant\Models\Organisation;
use App\Domains\AuthAndTenant\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_homepage_is_accessible_to_guests(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Landing/Index')
            ->has('auth.user')
            ->where('auth.user', null)
        );
    }

    public function test_public_homepage_does_not_expose_tenant_sensitive_data(): void
    {
        // Create background tenant data in the database
        $tenant = Tenant::create([
            'name' => 'Confidential Corp',
            'type' => 'ENTERPRISE',
            'status' => 'ACTIVE',
        ]);

        $org = Organisation::create([
            'tenant_id' => $tenant->id,
            'legal_name' => 'Confidential Corp (Pvt) Ltd',
        ]);

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Landing/Index')
            ->missing('tenant')
            ->missing('organisation')
            ->missing('memberships')
            ->missing('questionnaires')
            ->missing('compliance_documents')
        );

        // Ensure sensitive tenant strings are not leaked in the HTML response
        $response->assertDontSee('Confidential Corp (Pvt) Ltd');
    }

    public function test_authenticated_user_accessing_landing_page_receives_auth_user_prop(): void
    {
        $user = User::create([
            'name' => 'Tinashe Moyo',
            'email' => 'tinashe@moyo.co.zw',
            'password' => Hash::make('ValidPass123!'),
        ]);

        $tenant = Tenant::create([
            'name' => 'Moyo Industries',
            'type' => 'SME',
            'status' => 'ACTIVE',
        ]);

        $org = Organisation::create([
            'tenant_id' => $tenant->id,
            'legal_name' => 'Moyo Industries (Pvt) Ltd',
        ]);

        Membership::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'organisation_id' => $org->id,
            'role' => 'OWNER',
            'status' => 'ACTIVE',
        ]);

        $response = $this->actingAs($user)->get('/');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Landing/Index')
            ->has('auth.user', fn (Assert $userAssert) => $userAssert
                ->where('id', $user->id)
                ->where('name', 'Tinashe Moyo')
                ->where('email', 'tinashe@moyo.co.zw')
                ->etc()
            )
        );
    }

    public function test_unauthenticated_user_accessing_dashboard_is_redirected_to_login(): void
    {
        $response = $this->get('/dashboard');

        $response->assertRedirect('/login');
    }

    public function test_login_and_registration_routes_remain_operational_for_guests(): void
    {
        $loginResponse = $this->get('/login');
        $loginResponse->assertStatus(200);

        $registerResponse = $this->get('/register');
        $registerResponse->assertStatus(200);
    }

    public function test_authenticated_user_visiting_login_or_register_is_redirected_to_dashboard(): void
    {
        $user = User::create([
            'name' => 'Farai Chiweshe',
            'email' => 'farai@test.co.zw',
            'password' => Hash::make('Password123!'),
        ]);

        $loginResponse = $this->actingAs($user)->get('/login');
        $loginResponse->assertRedirect('/dashboard');

        $registerResponse = $this->actingAs($user)->get('/register');
        $registerResponse->assertRedirect('/dashboard');
    }
}
