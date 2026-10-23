<?php

declare(strict_types=1);

namespace Modules\Vending\tests\Feature;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Vending\Application\Actions\ApplyDynamicNearExpiryMarkdownAction;
use Modules\Vending\Application\Actions\DispenseSmartVendingCartAction;
use Modules\Vending\Application\Actions\ForecastVendingDemandAndWasteAction;
use Modules\Vending\Application\Actions\TriggerAutoReplenishmentPoAction;
use Modules\Vending\database\seeders\VendingDemoSeeder;
use Modules\Vending\Domain\Models\VendingUnit;
use Tests\TestCase;

class VendingEnterpriseDeepeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
        (new VendingDemoSeeder)->run();
    }

    public function test_forecast_vending_demand_and_waste_action_computes_projection(): void
    {
        $action = app(ForecastVendingDemandAndWasteAction::class);

        $result = $action->handle(
            outletCode: 'OUTLET-TEST-01',
            itemCode: 'BEV-TEST-01',
            forecastDate: '2026-11-01',
            footfallFactor: 1.25
        );

        $this->assertGreaterThan(0, $result['forecast_qty']);
        $this->assertGreaterThanOrEqual(0, $result['waste_forecast_qty']);
        $this->assertLessThanOrEqual(15.0, (float) $result['forecast']->mape);

        $this->assertDatabaseHas('ven_demand_forecasts', [
            'outlet_code' => 'OUTLET-TEST-01',
            'item_code' => 'BEV-TEST-01',
        ]);
    }

    public function test_trigger_auto_replenishment_po_action_generates_bounded_po(): void
    {
        $unit = VendingUnit::firstOrFail();
        $action = app(TriggerAutoReplenishmentPoAction::class);

        $result = $action->handle(
            vendingUnitId: $unit->id,
            itemCode: 'SNACK-RESTOCK-01',
            costPerUnitIdr: 12000
        );

        $this->assertNotNull($result['auto_po']);
        $this->assertEquals(50, $result['qty_ordered']);
        $this->assertEquals(600000, $result['estimated_total_idr']);
        $this->assertLessThanOrEqual(15000000, $result['estimated_total_idr']);

        $this->assertDatabaseHas('ven_auto_pos', [
            'item_code' => 'SNACK-RESTOCK-01',
            'status' => 'approved',
        ]);
    }

    public function test_dispense_smart_vending_cart_action_executes_dispense_transaction(): void
    {
        $unit = VendingUnit::firstOrFail();
        $action = app(DispenseSmartVendingCartAction::class);

        $result = $action->handle(
            vendingUnitId: $unit->id,
            itemCode: 'BEV-JUICE-01',
            qty: 2,
            unitPriceIdr: 15000
        );

        $this->assertStringStartsWith('TX-VEN-', $result['tx_code']);
        $this->assertEquals(30000, $result['total_price_idr']);

        $this->assertDatabaseHas('ven_vending_transactions', [
            'vending_unit_id' => $unit->id,
            'item_code' => 'BEV-JUICE-01',
            'qty' => 2,
            'price_idr' => 30000,
        ]);
    }

    public function test_apply_dynamic_near_expiry_markdown_action_tiers(): void
    {
        $action = app(ApplyDynamicNearExpiryMarkdownAction::class);

        // Tier: <= 6 hours -> 50% off
        $crit = $action->handle('PERISHABLE-01', 50000, 4);
        $this->assertEquals(50, $crit['discount_pct']);
        $this->assertEquals(25000, $crit['marked_down_price_idr']);

        // Tier: <= 24 hours -> 30% off
        $accel = $action->handle('PERISHABLE-01', 50000, 10);
        $this->assertEquals(30, $accel['discount_pct']);
        $this->assertEquals(35000, $accel['marked_down_price_idr']);

        // Tier: <= 48 hours -> 15% off
        $early = $action->handle('PERISHABLE-01', 50000, 30);
        $this->assertEquals(15, $early['discount_pct']);
        $this->assertEquals(42500, $early['marked_down_price_idr']);

        // Tier: > 48 hours -> 0% off
        $std = $action->handle('PERISHABLE-01', 50000, 72);
        $this->assertEquals(0, $std['discount_pct']);
        $this->assertEquals(50000, $std['marked_down_price_idr']);
    }

    public function test_demand_forecast_http_workflow(): void
    {
        $user = User::factory()->create(['role' => 'vending_operator']);

        $getResponse = $this->actingAs($user)->get(route('vending.operations.demand-forecast'));
        $getResponse->assertOk();
        $getResponse->assertSeeText('Holt-Winters');

        $postResponse = $this->actingAs($user)->post(route('vending.operations.demand-forecast.run'), [
            'outlet_code' => 'OUTLET-HTTP-01',
            'item_code' => 'ITEM-HTTP-01',
            'forecast_date' => '2026-11-15',
            'footfall_factor' => 1.1,
        ]);

        $postResponse->assertRedirect(route('vending.operations.demand-forecast'));
        $postResponse->assertSessionHas('success');
    }

    public function test_cart_dispense_http_workflow(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $unit = VendingUnit::firstOrFail();

        $getResponse = $this->actingAs($user)->get(route('vending.operations.cart-dispense'));
        $getResponse->assertOk();
        $getResponse->assertSeeText('Unmanned Smart Cart Dispense');

        $postResponse = $this->actingAs($user)->post(route('vending.operations.cart-dispense.dispense'), [
            'vending_unit_id' => $unit->id,
            'item_code' => 'SNACK-CHIP-01',
            'qty' => 1,
            'unit_price_idr' => 12000,
        ]);

        $postResponse->assertRedirect(route('vending.operations.cart-dispense'));
        $postResponse->assertSessionHas('success');
    }
}
