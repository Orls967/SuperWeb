<?php

declare(strict_types=1);

namespace Modules\Trade\tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Trade\Application\Actions\AuditBondedZoneInventoryMovementAction;
use Modules\Trade\Application\Actions\CalculateIncotermsLandedCostAction;
use Modules\Trade\Application\Actions\SubmitCustomsPibPebDeclarationAction;
use Modules\Trade\Domain\Models\Country;
use Modules\Trade\Domain\Models\ExportOrder;
use Modules\Trade\Domain\Models\ImportOrder;
use Modules\Trade\Domain\Models\Incoterm;
use Modules\Trade\Domain\Models\Port;
use Modules\Trade\Domain\Models\ShipmentLeg;
use Tests\TestCase;

class TradeOperationsSliceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Country $country;

    private Port $port;

    private Incoterm $incoterm;

    private ImportOrder $importOrder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->user = User::first() ?? User::factory()->create(['role' => 'admin']);

        $this->country = Country::firstOrCreate(
            ['code' => 'CN'],
            ['name' => 'China', 'currency_code' => 'USD', 'has_fta' => true]
        );

        $this->port = Port::firstOrCreate(
            ['code' => 'CNSHA'],
            ['name' => 'Port of Shanghai', 'country_code' => 'CN', 'type' => 'seaport']
        );

        $this->incoterm = Incoterm::firstOrCreate(
            ['code' => 'CIF'],
            ['name' => 'Cost Insurance and Freight', 'risk_transfer_point' => 'Vessel', 'cost_responsibility' => 'Seller']
        );

        $this->importOrder = ImportOrder::create([
            'order_number' => 'IMP-2026-TEST-01',
            'supplier_name' => 'Shanghai Packaging Co., Ltd.',
            'origin_country_code' => $this->country->code,
            'origin_port_id' => (string) $this->port->id,
            'incoterm_code' => $this->incoterm->code,
            'currency' => 'USD',
            'cif_foreign_amount' => 5000000,
            'cif_idr' => 775000000,
            'status' => 'ordered',
        ]);
    }

    public function test_calculate_incoterms_landed_cost_computes_exact_duties_and_taxes(): void
    {
        $action = app(CalculateIncotermsLandedCostAction::class);

        // CIF: 1,000,000,000 IDR
        // BM 5%: 50,000,000 -> Nilai Impor: 1,050,000,000
        // PPN 11%: 1,050,000,000 * 11% = 115,500,000
        // PPh 22 (2.5%): 1,050,000,000 * 2.5% = 26,250,000
        // Total landed: 1,000,000,000 + 50,000,000 + 115,500,000 + 26,250,000 = 1,191,750,000 IDR
        $res = $action->execute(
            importOrder: $this->importOrder,
            cifIdr: 1000000000,
            dutyPercent: 5.0,
            vatPercent: 11.0,
            pph22Percent: 2.5
        );

        $this->assertSame(50000000, $res['bm_idr']);
        $this->assertSame(115500000, $res['ppn_idr']);
        $this->assertSame(26250000, $res['pph22_idr']);
        $this->assertSame(1191750000, $res['total_landed_cost_idr']);
        $this->assertSame(1191750000, (int) $this->importOrder->fresh()->total_landed_cost_idr);
    }

    public function test_calculate_incoterms_landed_cost_validates_cif_greater_than_zero(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $action = app(CalculateIncotermsLandedCostAction::class);
        $action->execute($this->importOrder, cifIdr: 0);
    }

    public function test_submit_customs_pib_declaration_for_import_order(): void
    {
        $action = app(SubmitCustomsPibPebDeclarationAction::class);
        $res = $action->execute($this->importOrder, customsOfficeCode: '040300');

        $this->assertStringStartsWith('PIB-040300-', $res['declaration_number']);
        $this->assertSame('PIB_IMPORT', $res['type']);
        $this->assertSame('customs_cleared', $res['status']);
        $this->assertSame('customs_cleared', $this->importOrder->fresh()->status);
    }

    public function test_submit_customs_peb_declaration_for_export_order(): void
    {
        $exportOrder = ExportOrder::create([
            'order_number' => 'EXP-2026-TEST-01',
            'buyer_name' => 'Tokyo Retailers Ltd',
            'destination_country_code' => $this->country->code,
            'destination_port_id' => (string) $this->port->id,
            'incoterm_code' => $this->incoterm->code,
            'currency' => 'USD',
            'total_foreign_amount' => 10000000,
            'total_functional_idr' => 1550000000,
            'status' => 'confirmed',
        ]);

        $action = app(SubmitCustomsPibPebDeclarationAction::class);
        $res = $action->execute($exportOrder, customsOfficeCode: '040300');

        $this->assertStringStartsWith('PEB-040300-', $res['declaration_number']);
        $this->assertSame('PEB_EXPORT', $res['type']);
        $this->assertSame('shipped', $res['status']);
        $this->assertNotNull($exportOrder->fresh()->risk_transferred_at);
    }

    public function test_audit_bonded_zone_inventory_movement_verdict(): void
    {
        $this->importOrder->update([
            'pib_number' => 'PIB-040300-2026-99999',
            'status' => 'customs_cleared',
            'coo_verified' => true,
            'total_landed_cost_idr' => 500000000,
        ]);

        ShipmentLeg::create([
            'order_reference_no' => $this->importOrder->order_number,
            'leg_stage' => 'port_loading',
            'location_name' => 'Shanghai Port Terminal 1',
            'hash' => hash('sha256', 'leg-1-salt'),
            'recorded_at' => now(),
        ]);

        $action = app(AuditBondedZoneInventoryMovementAction::class);
        $res = $action->execute($this->importOrder);

        $this->assertTrue($res['is_bonded_compliant']);
        $this->assertTrue($res['cleared_customs']);
        $this->assertSame(1, $res['total_legs']);
        $this->assertSame('audit_passed_with_preferential_fta', $res['audit_verdict']);
    }

    public function test_trade_customs_controller_endpoints(): void
    {
        $this->actingAs($this->user);

        $respLanded = $this->postJson("/trade/customs/import/{$this->importOrder->id}/landed-cost", [
            'cif_idr' => 500000000,
            'duty_percent' => 5.0,
            'vat_percent' => 11.0,
            'pph22_percent' => 2.5,
        ]);
        $respLanded->assertOk();
        $respLanded->assertJsonFragment(['bm_idr' => 25000000]);

        $respPib = $this->postJson("/trade/customs/import/{$this->importOrder->id}/pib");
        $respPib->assertOk();
        $respPib->assertJsonFragment(['status' => 'customs_cleared']);

        $respAudit = $this->getJson("/trade/customs/bonded-zone/{$this->importOrder->id}/audit");
        $respAudit->assertOk();
        $respAudit->assertJsonFragment(['is_bonded_compliant' => true]);
    }
}
