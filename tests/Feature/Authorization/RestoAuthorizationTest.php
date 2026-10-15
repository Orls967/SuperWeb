<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\database\seeders\BankingSeeder;
use Modules\Core\database\seeders\RbacSeeder;
use Modules\Resto\Application\Actions\PaySupplierAction;
use Modules\Resto\database\seeders\RestoMenuSeeder;
use Modules\Resto\Domain\Models\Supplier;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class RestoAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
        $this->seed(RestoMenuSeeder::class);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/resto/pos')->assertRedirect('/login');
        $this->get('/resto/kitchen')->assertRedirect('/login');
        $this->get('/resto/menu')->assertRedirect('/login');
    }

    public function test_unauthorized_roles_receive_403_on_resto_mutations(): void
    {
        $auditor = User::factory()->create(['role' => 'auditor']);
        $customer = User::factory()->create(['role' => 'customer']);

        foreach ([$auditor, $customer] as $user) {
            $this->actingAs($user)->post('/resto/purchases/1/pay', ['amount' => 100000])
                ->assertForbidden();

            $this->actingAs($user)->post('/resto/kitchen/cook')
                ->assertForbidden();

            $this->actingAs($user)->post('/resto/pos/order/1/void')
                ->assertForbidden();

            $this->actingAs($user)->post('/resto/franchise/royalty')
                ->assertForbidden();
        }
    }

    public function test_auditor_has_read_only_access_to_resto_reports_and_catalogs(): void
    {
        $auditor = User::factory()->create(['role' => 'auditor']);

        // Read is allowed
        $this->actingAs($auditor)->get('/resto/analytics')->assertOk();
        $this->actingAs($auditor)->get('/resto/menu')->assertOk();
        $this->actingAs($auditor)->get('/resto/ingredients')->assertOk();
        $this->actingAs($auditor)->get('/resto/purchases')->assertOk();
        $this->actingAs($auditor)->get('/resto/outlets')->assertOk();

        // Write is strictly forbidden (403)
        $this->actingAs($auditor)->post('/resto/menu')->assertForbidden();
        $this->actingAs($auditor)->post('/resto/ingredients')->assertForbidden();
        $this->actingAs($auditor)->post('/resto/purchases/1/pay')->assertForbidden();
        $this->actingAs($auditor)->post('/resto/stock-counts/1/approve')->assertForbidden();
    }

    public function test_cashier_and_kitchen_restricted_to_operational_endpoints(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);
        $kitchen = User::factory()->create(['role' => 'kitchen']);

        // Cashier can access POS, but not Kitchen or Purchases
        $this->actingAs($cashier)->get('/resto/pos')->assertOk();
        $this->actingAs($cashier)->get('/resto/kitchen')->assertForbidden();
        $this->actingAs($cashier)->get('/resto/purchases')->assertForbidden();

        // Kitchen can access Kitchen, but not POS or Purchases
        $this->actingAs($kitchen)->get('/resto/kitchen')->assertOk();
        $this->actingAs($kitchen)->get('/resto/pos')->assertForbidden();
        $this->actingAs($kitchen)->get('/resto/purchases')->assertForbidden();
    }

    public function test_outlet_manager_and_admin_have_full_management_access(): void
    {
        $outletManager = User::factory()->create(['role' => 'outlet_manager']);
        $admin = User::factory()->create(['role' => 'admin']);

        foreach ([$outletManager, $admin] as $user) {
            $this->actingAs($user)->get('/resto/pos')->assertOk();
            $this->actingAs($user)->get('/resto/kitchen')->assertOk();
            $this->actingAs($user)->get('/resto/menu')->assertOk();
            $this->actingAs($user)->get('/resto/purchases')->assertOk();
            $this->actingAs($user)->get('/resto/analytics')->assertOk();
        }
    }

    public function test_pay_supplier_action_requires_authorized_role(): void
    {
        $this->seed(BankingSeeder::class);

        $supplier = Supplier::create([
            'name' => 'PT Suplai Bahan Segar',
            'contact' => 'Budi Santoso - 0812345678',
            'terms_days' => 30,
            'is_active' => true,
        ]);

        $action = app(PaySupplierAction::class);

        $customer = User::factory()->create(['role' => 'customer']);
        $this->actingAs($customer);

        $this->expectException(HttpException::class);
        $action->handle(supplier: $supplier, amount: 500000);
    }
}
