<?php

namespace Modules\Manufacturing\tests\Feature\C2m;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Manufacturing\Application\Services\C2m\VmiAndC2mService;
use Modules\Manufacturing\Domain\Models\C2m\VmiReplenishment;
use Tests\TestCase;

class VmiAndC2mManufacturingTest extends TestCase
{
    use RefreshDatabase;

    protected VmiAndC2mService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(VmiAndC2mService::class);

        LedgerAccount::create([
            'code' => 'mfg:vmi_commitment:IDR',
            'name' => 'VMI Purchase Commitment',
            'asset_code' => 'IDR',
            'kind' => 'asset',
            'allow_negative' => true,
            'cached_balance' => '0',
        ]);

        LedgerAccount::create([
            'code' => 'mfg:vmi_budget_encumbrance:IDR',
            'name' => 'VMI Budget Encumbrance',
            'asset_code' => 'IDR',
            'kind' => 'liability',
            'allow_negative' => true,
            'cached_balance' => '0',
        ]);
    }

    public function test_82_1_vmi_reorder_point_triggers_auto_po_and_commits_budget(): void
    {
        // 1. Stock 15 <= reorder point 20 -> auto ordered (within plafond)
        $vmi1 = $this->service->evaluateVmiTrigger(
            supplierId: 501,
            sku: 'FASTENER-M8-TITANIUM',
            currentStock: 15,
            reorderPoint: 20,
            reorderQty: 100,
            unitCostIdr: 50_000, // 5,000,000 IDR <= 50,000,000 IDR plafond
            contractPlafondIdr: 50_000_000
        );

        $this->assertNotNull($vmi1);
        $this->assertEquals('AUTO_ORDERED', $vmi1->status);
        $this->assertEquals(5_000_000, $vmi1->total_estimated_idr);

        // Check budget commitment in ledger
        $comm = LedgerAccount::where('code', 'mfg:vmi_commitment:IDR')->first();
        $enc = LedgerAccount::where('code', 'mfg:vmi_budget_encumbrance:IDR')->first();
        $this->assertEquals('5000000', (string) $comm->cached_balance);
        $this->assertEquals('-5000000', (string) $enc->cached_balance);

        // 2. Duplicate trigger on same day is idempotent
        $vmi2 = $this->service->evaluateVmiTrigger(
            supplierId: 501,
            sku: 'FASTENER-M8-TITANIUM',
            currentStock: 12,
            reorderPoint: 20,
            reorderQty: 100,
            unitCostIdr: 50_000,
            contractPlafondIdr: 50_000_000
        );
        $this->assertEquals($vmi1->id, $vmi2->id);
        $this->assertEquals(1, VmiReplenishment::where('sku', 'FASTENER-M8-TITANIUM')->count());

        // 3. Stock above reorder point does not trigger
        $vmiNone = $this->service->evaluateVmiTrigger(
            supplierId: 501,
            sku: 'OTHER-PART',
            currentStock: 50,
            reorderPoint: 20,
            reorderQty: 100,
            unitCostIdr: 50_000
        );
        $this->assertNull($vmiNone);

        // 4. Exceeding contract plafond requires approval
        $vmiHigh = $this->service->evaluateVmiTrigger(
            supplierId: 502,
            sku: 'EXPENSIVE-TURBINE-BLADE',
            currentStock: 5,
            reorderPoint: 10,
            reorderQty: 20,
            unitCostIdr: 5_000_000, // 100,000,000 IDR > 50jt plafond
            contractPlafondIdr: 50_000_000
        );
        $this->assertEquals('PENDING_APPROVAL', $vmiHigh->status);
    }

    public function test_82_3_and_82_4_c2m_configurator_bom_cost_roll_up_and_spk_routing(): void
    {
        $bomCosts = [
            'RAW_ALUMINUM_7075' => 1_500_000,
            'CNC_TOOLING_WEAR' => 300_000,
            'HARDWARE_BOLTS' => 200_000,
        ]; // Base BOM = 2,000,000 IDR

        $customOrder = $this->service->configureAndQuoteC2m(
            userId: 99,
            baseModel: 'CUSTOM_CONTROL_ARM',
            parameters: [
                'size_mm' => 350,
                'finish' => 'TITANIUM_COATED', // +35% complexity
                'tolerance' => 'AEROSPACE_PRECISION', // +25% complexity
            ],
            bomItemsCost: $bomCosts
        );

        // Total complexity = 1.0 + 0.35 + 0.25 = 1.60
        $this->assertEquals(1.60, (float) $customOrder->complexity_factor);
        // Rolled up BOM = 2,000,000 * 1.60 = 3,200,000 IDR
        $this->assertEquals(3_200_000, $customOrder->rolled_up_bom_cost_idr);
        // Final price = 3,200,000 * 1.30 = 4,160,000 IDR
        $this->assertEquals(4_160_000, $customOrder->final_price_idr);
        $this->assertEquals('DESIGN_VALIDATED', $customOrder->status);

        // Issue factory SPK
        $issued = $this->service->issueFactorySpk($customOrder);
        $this->assertEquals('SPK_ISSUED', $issued->status);
        $this->assertNotNull($issued->spk_number);
    }
}
