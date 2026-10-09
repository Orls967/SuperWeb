<?php

declare(strict_types=1);

namespace Modules\Trade\tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Trade\Application\Services\TradeService;
use Modules\Treasury\Application\Services\TreasuryService;
use Tests\TestCase;

class TradeTest extends TestCase
{
    use RefreshDatabase;

    protected TradeService $tradeService;

    protected TreasuryService $treasuryService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tradeService = app(TradeService::class);
        $this->treasuryService = app(TreasuryService::class);

        // Setup FX rates: 1 USD = 15,000 IDR
        $this->treasuryService->registerCurrency('USD', 'US Dollar');
        $this->treasuryService->registerCurrency('IDR', 'Indonesian Rupiah');
        $this->treasuryService->recordExchangeRate('USD', 'IDR', '2026-10-06', 15_000_000_000, 1_000_000);
    }

    public function test_49_1_master_trade_setup(): void
    {
        $id = $this->tradeService->registerCountry('ID', 'Indonesia', 'IDR');
        $cn = $this->tradeService->registerCountry('CN', 'China', 'CNY', hasFta: true);

        $portJkt = $this->tradeService->registerPort('IDTPP', 'Tanjung Priok', 'ID', 'seaport');
        $fob = $this->tradeService->registerIncoterm('FOB', 'Free On Board', 'On board vessel at port of origin', 'Buyer pays freight and insurance');

        $hs = $this->tradeService->registerHsCode(
            hsCode: '8703.23.91',
            desc: 'Passenger motor vehicle CBU',
            baseDuty: 10.0,
            ftaRate: 0.0,
            isLartas: true,
            permit: 'KEMENDAG_SURAT_PERSETUJUAN_IMPOR'
        );

        $this->assertDatabaseHas('trd_countries', ['code' => 'CN', 'has_fta' => 1]);
        $this->assertDatabaseHas('trd_ports', ['code' => 'IDTPP']);
        $this->assertDatabaseHas('trd_incoterms', ['code' => 'FOB']);
        $this->assertDatabaseHas('trd_hs_codes', ['hs_code' => '8703.23.91', 'is_lartas' => 1]);
    }

    public function test_49_2_export_order_lifecycle_and_revenue_recognition(): void
    {
        $this->tradeService->registerCountry('SG', 'Singapore', 'SGD');
        $port = $this->tradeService->registerPort('SGSIN', 'Port of Singapore', 'SG');
        $this->tradeService->registerIncoterm('FOB', 'Free On Board', 'Port Loading', 'Buyer');

        // Create export order of 20,000 USD (300,000,000 IDR)
        $order = $this->tradeService->createExportOrder(
            buyerName: 'Lion City Logistics Pte Ltd',
            destCountryCode: 'SG',
            destPortId: $port->id,
            incotermCode: 'FOB',
            foreignAmount: 20_000,
            currency: 'USD'
        );

        $this->assertEquals(300_000_000, $order->total_functional_idr);
        $this->assertEquals('draft', $order->status);

        // Confirm PEB
        $order = $this->tradeService->confirmExportPeb($order, 'PEB-008912-2026');
        $this->assertEquals('confirmed', $order->status);

        // Recognize export revenue upon risk transfer
        $order = $this->tradeService->recognizeExportRevenue($order, 'EXP_REV_001');
        $this->assertEquals('risk_transferred', $order->status);
        $this->assertNotNull($order->risk_transferred_at);

        // Idempotency: call again
        $orderAgain = $this->tradeService->recognizeExportRevenue($order, 'EXP_REV_001');
        $this->assertEquals('risk_transferred', $orderAgain->status);
    }

    public function test_49_3_and_49_5_import_order_customs_calculator_and_fta_preference(): void
    {
        $this->tradeService->registerCountry('CN', 'China', 'CNY', hasFta: true);
        $port = $this->tradeService->registerPort('CNSHA', 'Shanghai Port', 'CN');
        $this->tradeService->registerIncoterm('CIF', 'Cost, Insurance and Freight', 'Discharge Port', 'Seller');

        $hs = $this->tradeService->registerHsCode(
            hsCode: '8409.91.00',
            desc: 'Automotive Engine Parts',
            baseDuty: 7.5,
            ftaRate: 0.0
        );

        // Import order: CIF 10,000 USD = 150,000,000 IDR
        // 1. Without valid CoO -> Base duty 7.5% = 11,250,000 IDR
        $taxNormal = $this->tradeService->calculateImportDuties(150_000_000, $hs, false);
        $this->assertEquals(11_250_000, $taxNormal['customs_duty_bm_idr']);
        $this->assertFalse($taxNormal['is_preferential']);

        // 2. With valid CoO Form E -> 0% preferential duty rate
        $taxFta = $this->tradeService->calculateImportDuties(150_000_000, $hs, true);
        $this->assertEquals(0, $taxFta['customs_duty_bm_idr']);
        $this->assertTrue($taxFta['is_preferential']);

        // Create import order with FTA CoO verified
        $order = $this->tradeService->createImportOrder(
            supplierName: 'Shanghai Auto Component Mfg',
            originCountryCode: 'CN',
            originPortId: $port->id,
            incotermCode: 'CIF',
            cifForeignAmount: 10_000,
            currency: 'USD',
            hsCode: $hs,
            hasValidCoo: true
        );

        $this->assertTrue($order->coo_verified);
        $this->assertEquals(0, $order->customs_duty_bm_idr);
        $this->assertDatabaseHas('trd_import_orders', [
            'order_number' => $order->order_number,
            'coo_verified' => 1,
        ]);
    }

    public function test_49_4_trade_document_attachment(): void
    {
        $this->tradeService->registerCountry('ID', 'Indonesia');
        $port = $this->tradeService->registerPort('IDTPP', 'Tanjung Priok', 'ID');
        $this->tradeService->registerIncoterm('FOB', 'FOB', 'Port', 'Buyer');

        $order = $this->tradeService->createExportOrder('Test Buyer', 'ID', $port->id, 'FOB', 5000);

        $doc = $this->tradeService->attachTradeDocument(
            documentableType: 'export_order',
            documentableId: $order->id,
            docType: 'coo_form_e',
            certNo: 'COO-ACFTA-2026-999',
            issuingAuthority: 'Ministry of Trade RI',
            issueDate: '2026-10-06'
        );

        $this->assertDatabaseHas('trd_trade_documents', [
            'certificate_number' => 'COO-ACFTA-2026-999',
            'doc_type' => 'coo_form_e',
            'is_verified' => 1,
        ]);
    }

    public function test_49_6_cross_border_tracking_hash_chain(): void
    {
        $orderRef = 'EXP-SAMPLE-01';

        $leg1 = $this->tradeService->recordShipmentLeg($orderRef, 'origin', 'Factory Warehouse Bekasi');
        $leg2 = $this->tradeService->recordShipmentLeg($orderRef, 'port_loading', 'Tanjung Priok Container Terminal');
        $leg3 = $this->tradeService->recordShipmentLeg($orderRef, 'customs', 'Customs Inspection Area');

        $this->assertEquals('GENESIS_CROSS_BORDER_TRACK', $leg1->previous_hash);
        $this->assertEquals($leg1->hash, $leg2->previous_hash);
        $this->assertEquals($leg2->hash, $leg3->previous_hash);

        // Verify chain
        $this->assertTrue($this->tradeService->verifyCrossBorderChain($orderRef));
    }

    public function test_49_7_and_49_9_trade_disputes_and_audit(): void
    {
        $dispute = $this->tradeService->fileDispute(
            orderRefNo: 'IMP-DAMAGED-99',
            reason: 'damaged',
            claimAmountIdr: 45_000_000
        );

        $this->assertEquals('submitted', $dispute->status);

        $settled = $this->tradeService->settleDisputeWithInsurance($dispute, 40_000_000);
        $this->assertEquals('settled', $settled->status);
        $this->assertEquals(40_000_000, $settled->insurance_payout_idr);

        // Run Trade Audit
        $audit = $this->tradeService->auditTrade();
        $this->assertEquals('OK', $audit['status']);
        $this->assertEquals(0, $audit['broken_chains_count']);

        $this->artisan('trade:audit')
            ->expectsOutputToContain('Trade audit PASSED with 0 discrepancy.')
            ->assertExitCode(0);
    }

    public function test_trade_web_route_accessible_by_authenticated_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('trade.index'))
            ->assertOk()
            ->assertSee('Cross-Border Trade Operations');
    }
}
