<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\database\seeders\RbacSeeder;
use Tests\TestCase;

class FinanceHcmAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/hcm')->assertRedirect('/login');
        $this->get('/treasury')->assertRedirect('/login');
        $this->get('/enterprise-finance')->assertRedirect('/login');
    }

    public function test_unauthorized_roles_receive_403_on_hcm(): void
    {
        $auditor = User::factory()->create(['role' => 'auditor']);
        $customer = User::factory()->create(['role' => 'customer']);
        $mekanik = User::factory()->create(['role' => 'mekanik']);

        foreach ([$auditor, $customer, $mekanik] as $user) {
            $this->actingAs($user)->get('/hcm')->assertForbidden();
        }
    }

    public function test_unauthorized_roles_receive_403_on_treasury_and_enterprise_finance(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $mekanik = User::factory()->create(['role' => 'mekanik']);
        $cashier = User::factory()->create(['role' => 'cashier']);

        foreach ([$customer, $mekanik, $cashier] as $user) {
            $this->actingAs($user)->get('/treasury')->assertForbidden();
            $this->actingAs($user)->get('/enterprise-finance')->assertForbidden();
            $this->actingAs($user)->get('/enterprise-finance/budgets')->assertForbidden();
        }
    }

    public function test_authorized_roles_can_access_hcm_treasury_and_enterprise_finance(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $hcmManager = User::factory()->create(['role' => 'hcm_manager']);
        $treasury = User::factory()->create(['role' => 'treasury']);
        $auditor = User::factory()->create(['role' => 'auditor']);

        // HCM: admin & hcm_manager
        $this->actingAs($admin)->get('/hcm')->assertOk();
        $this->actingAs($hcmManager)->get('/hcm')->assertOk();

        // Treasury: admin, treasury, auditor
        $this->actingAs($admin)->get('/treasury')->assertOk();
        $this->actingAs($treasury)->get('/treasury')->assertOk();
        $this->actingAs($auditor)->get('/treasury')->assertOk();

        // Enterprise Finance: admin, treasury, auditor
        $this->actingAs($admin)->get('/enterprise-finance')->assertOk();
        $this->actingAs($treasury)->get('/enterprise-finance')->assertOk();
        $this->actingAs($auditor)->get('/enterprise-finance')->assertOk();
        $this->actingAs($treasury)->get('/enterprise-finance/budgets')->assertOk();
    }
}
