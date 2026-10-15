<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\database\seeders\RbacSeeder;
use Modules\Mall\Application\Actions\MallAutoDebitAction;
use Modules\Mall\database\seeders\MallSeeder;
use Modules\Mall\Domain\Enums\TenantCategory;
use Modules\Mall\Domain\Models\Invoice;
use Modules\Mall\Domain\Models\Tenant;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class MallAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
        $this->seed(MallSeeder::class);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/mall/billing');
        $response->assertRedirect('/login');

        $response = $this->get('/mall/portal');
        $response->assertRedirect('/login');
    }

    public function test_unauthorized_roles_receive_403_on_billing_mutations(): void
    {
        // Auditor, tenant, customer must be forbidden from billing generation and auto-debit
        $auditor = User::factory()->create(['role' => 'auditor']);
        $tenant = User::factory()->create(['role' => 'tenant']);
        $customer = User::factory()->create(['role' => 'customer']);

        foreach ([$auditor, $tenant, $customer] as $user) {
            $this->actingAs($user)->post('/mall/billing/generate')
                ->assertForbidden();

            $this->actingAs($user)->post('/mall/billing/apply-penalties')
                ->assertForbidden();

            $this->actingAs($user)->post('/mall/invoices/1/auto-debit')
                ->assertForbidden();
        }
    }

    public function test_auditor_has_read_only_access_to_mall_billing_and_leases(): void
    {
        $auditor = User::factory()->create(['role' => 'auditor']);

        // Read is allowed
        $this->actingAs($auditor)->get('/mall/billing')
            ->assertOk();
        $this->actingAs($auditor)->get('/mall/leases')
            ->assertOk();
        $this->actingAs($auditor)->get('/mall/tenants')
            ->assertOk();
        $this->actingAs($auditor)->get('/mall/site-plan')
            ->assertOk();

        // Write is strictly forbidden (403)
        $this->actingAs($auditor)->post('/mall/leases/1/activate')
            ->assertForbidden();
        $this->actingAs($auditor)->post('/mall/leases/1/terminate')
            ->assertForbidden();
        $this->actingAs($auditor)->post('/mall/leases/1/renew')
            ->assertForbidden();
        $this->actingAs($auditor)->post('/mall/tenants')
            ->assertForbidden();
    }

    public function test_tenant_portal_restricted_to_tenant_and_admin(): void
    {
        $tenantUser = User::factory()->create(['role' => 'tenant']);
        Tenant::create([
            'user_id' => $tenantUser->id,
            'company_name' => 'PT Mitra Tenant Sukses',
            'brand_name' => 'Tenant Cafe',
            'category' => TenantCategory::FNB,
            'pic_name' => 'Budi',
            'pic_email' => 'tenant@test.com',
            'pic_phone' => '08123456789',
            'is_active' => true,
        ]);

        $admin = User::factory()->create(['role' => 'admin']);
        $auditor = User::factory()->create(['role' => 'auditor']);
        $customer = User::factory()->create(['role' => 'customer']);

        $this->actingAs($tenantUser)->get('/mall/portal')->assertOk();
        $this->actingAs($admin)->get('/mall/portal')->assertOk();
        $this->actingAs($auditor)->get('/mall/portal')->assertForbidden();
        $this->actingAs($customer)->get('/mall/portal')->assertForbidden();
    }

    public function test_mall_admin_can_access_billing_and_operations(): void
    {
        $mallAdmin = User::factory()->create(['role' => 'mall_admin']);

        $this->actingAs($mallAdmin)->get('/mall/billing')->assertOk();
        $this->actingAs($mallAdmin)->get('/mall/leases')->assertOk();
        $this->actingAs($mallAdmin)->get('/mall/facilities')->assertOk();
        $this->actingAs($mallAdmin)->get('/mall/parking')->assertOk();
    }

    public function test_mall_auto_debit_action_requires_mall_admin_or_admin(): void
    {
        $invoice = Invoice::firstOrFail();

        $action = app(MallAutoDebitAction::class);

        // Caller without mall_admin or admin role must be forbidden
        $customer = User::factory()->create(['role' => 'customer']);
        $this->actingAs($customer);

        $this->expectException(HttpException::class);
        $action->execute($invoice);
    }
}
