<?php

namespace Tests\Feature;

use App\Domains\AuthAndTenant\Models\Membership;
use App\Domains\AuthAndTenant\Models\Organisation;
use App\Domains\AuthAndTenant\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavigationWorkspacesTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Tenant $tenant;
    protected Organisation $org;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Fungai Sibanda',
            'email' => 'fungai@navtest.co.zw',
            'password' => bcrypt('Password123!'),
        ]);

        $this->tenant = Tenant::create([
            'name' => 'Sibanda Holdings',
            'type' => 'SME',
            'status' => 'ACTIVE',
        ]);

        $this->org = Organisation::create([
            'tenant_id' => $this->tenant->id,
            'legal_name' => 'Sibanda Holdings (Pvt) Ltd',
            'base_currency' => 'USD',
        ]);

        Membership::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'organisation_id' => $this->org->id,
            'role' => 'ADMIN',
            'status' => 'ACTIVE',
        ]);
    }

    public function test_guest_is_redirected_to_login_on_protected_routes(): void
    {
        $response = $this->get('/dashboard');
        $response->assertRedirect('/login');

        $response = $this->get('/registration');
        $response->assertRedirect('/login');

        $response = $this->get('/comply');
        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_can_access_all_core_navigation_workspaces(): void
    {
        $this->actingAs($this->user);

        $routes = [
            '/dashboard',
            '/registration',
            '/comply',
            '/renew',
            '/tender',
            '/documents',
            '/reports',
            '/settings',
        ];

        foreach ($routes as $route) {
            $response = $this->get($route);
            $response->assertStatus(200);
        }
    }
}
