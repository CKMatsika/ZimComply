<?php

namespace Tests\Feature;

use App\Domains\AuthAndTenant\Models\Membership;
use App\Domains\AuthAndTenant\Models\Organisation;
use App\Domains\AuthAndTenant\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_renders_successfully(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::create([
            'name' => 'Kudakwashe Dube',
            'email' => 'kuda@test.co.zw',
            'password' => Hash::make('SecretPass123!'),
        ]);

        $tenant = Tenant::create([
            'name' => 'Dube Logistics',
            'type' => 'SME',
            'status' => 'ACTIVE',
        ]);

        $org = Organisation::create([
            'tenant_id' => $tenant->id,
            'legal_name' => 'Dube Logistics (Pvt) Ltd',
        ]);

        Membership::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'organisation_id' => $org->id,
            'role' => 'OWNER',
            'status' => 'ACTIVE',
        ]);

        $response = $this->post('/login', [
            'email' => 'kuda@test.co.zw',
            'password' => 'SecretPass123!',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect('/dashboard');
    }

    public function test_user_cannot_login_with_invalid_credentials(): void
    {
        $response = $this->post('/login', [
            'email' => 'nonexistent@company.co.zw',
            'password' => 'wrongpassword',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
    }

    public function test_new_sme_registration_initializes_tenant_hierarchy(): void
    {
        $response = $this->post('/register', [
            'name' => 'Rudo Shumba',
            'email' => 'rudo@shumbafarms.co.zw',
            'phone' => '+263 77 999 8888',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'company_name' => 'Shumba Agro Enterprises (Pvt) Ltd',
            'entity_type' => 'PRIVATE_LIMITED_COMPANY',
            'account_type' => 'SME',
        ]);

        $response->assertRedirect('/dashboard');

        $user = User::where('email', 'rudo@shumbafarms.co.zw')->first();
        $this->assertNotNull($user);
        $this->assertAuthenticatedAs($user);

        $tenant = Tenant::where('name', 'Shumba Agro Enterprises (Pvt) Ltd')->first();
        $this->assertNotNull($tenant);

        $org = Organisation::where('tenant_id', $tenant->id)->first();
        $this->assertNotNull($org);
        $this->assertEquals('Shumba Agro Enterprises (Pvt) Ltd', $org->legal_name);

        $membership = Membership::where('user_id', $user->id)->first();
        $this->assertNotNull($membership);
        $this->assertEquals('OWNER', $membership->role);
    }
}
