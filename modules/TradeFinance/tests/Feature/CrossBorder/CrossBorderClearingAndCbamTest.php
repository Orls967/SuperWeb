<?php

namespace Modules\TradeFinance\tests\Feature\CrossBorder;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Core\Domain\Models\Role;
use Modules\TradeFinance\Application\Services\CrossBorder\CrossBorderClearingService;
use Modules\TradeFinance\Domain\Models\CrossBorder\CrossBorderEscrow;
use Tests\TestCase;

class CrossBorderClearingAndCbamTest extends TestCase
{
    use RefreshDatabase;

    protected CrossBorderClearingService $service;

    protected User $admin;

    protected User $treasury;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
        $this->service = app(CrossBorderClearingService::class);

        Role::firstOrCreate(['name' => 'admin'], ['label' => 'Administrator', 'is_system' => true]);
        Role::firstOrCreate(['name' => 'treasury'], ['label' => 'Treasury', 'is_system' => true]);

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->treasury = User::factory()->create(['role' => 'treasury']);
    }

    public function test_83_1_and_83_2_stablecoin_escrow_deposit_anti_duplicate_bl_and_release(): void
    {
        // 1. Initial Deposit: $10,000 USD (1,000,000 cents) @ 16,000 IDR = 160,000,000 IDR
        $escrow = $this->service->depositEscrow(
            importerId: 101,
            exporterId: 202,
            amountCents: 1_000_000,
            lockedFxRateIdr: 16_000,
            blNumber: 'MAEU-9920192',
            blDocument: 'PDF_BILL_OF_LADING_SIGNED_BY_CARRIER'
        );

        $this->assertEquals('DEPOSITED', $escrow->status);
        $this->assertEquals(1_000_000, $escrow->amount_cents);
        $this->assertEquals(16_000, $escrow->locked_fx_rate_idr);

        // Check ledger deposit: Escrow holding +160jt, Importer wallet -160jt
        $escrowAcc = LedgerAccount::where('code', 'tf:crossborder_escrow:IDR')->first();
        $this->assertEquals('160000000', (string) $escrowAcc->cached_balance);

        // Duplicate BL is rejected
        $this->expectException(\RuntimeException::class);
        $this->service->depositEscrow(
            importerId: 103,
            exporterId: 205,
            amountCents: 500_000,
            lockedFxRateIdr: 16_000,
            blNumber: 'MAEU-9920192', // Duplicate BL
            blDocument: 'PDF_OTHER'
        );
    }

    public function test_83_1_verified_pod_releases_escrow_to_exporter(): void
    {
        $escrow = $this->service->depositEscrow(
            importerId: 101,
            exporterId: 202,
            amountCents: 500_000, // $5,000 USD = 80,000,000 IDR
            lockedFxRateIdr: 16_000,
            blNumber: 'MSCU-883311',
            blDocument: 'PDF_BL_DOC'
        );

        $podHash = 'HASH_LOGISTICS_CHAIN_OF_CUSTODY_VERIFIED_POD_123';
        $released = $this->service->releaseEscrowOnDelivery($escrow, $podHash);

        $this->assertEquals('RELEASED', $released->status);
        $this->assertEquals($podHash, $released->pod_chain_hash);
        $this->assertNotNull($released->released_at);

        // Verify ledger: Escrow holding balanced back to 0, Exporter settlement +80,000,000 IDR
        $escrowAcc = LedgerAccount::where('code', 'tf:crossborder_escrow:IDR')->first();
        $exporterAcc = LedgerAccount::where('code', 'tf:exporter_settlement:IDR')->first();
        $this->assertEquals('0', (string) $escrowAcc->cached_balance);
        $this->assertEquals('80000000', (string) $exporterAcc->cached_balance);
    }

    public function test_83_2_dispute_window_hold_and_resolution_refund(): void
    {
        $escrow = $this->service->depositEscrow(
            importerId: 105,
            exporterId: 208,
            amountCents: 200_000, // $2,000 USD @ 16,000 = 32,000,000 IDR
            lockedFxRateIdr: 16_000,
            blNumber: 'CMA-DISPUTE-01',
            blDocument: 'PDF_DOC_CMA'
        );

        // Trigger dispute hold within window
        $disputed = $this->service->holdDispute($escrow, 'Damage reported on container seal');
        $this->assertEquals('DISPUTED', $disputed->status);
        $this->assertNotNull($disputed->hold_until);

        // Resolve dispute with refund
        $refunded = $this->service->resolveDispute($disputed, 'REFUND');
        $this->assertEquals('REFUNDED', $refunded->status);

        // Verify ledger balances after refund
        $escrowAcc = LedgerAccount::where('code', 'tf:crossborder_escrow:IDR')->first();
        $importerAcc = LedgerAccount::where('code', 'tf:importer_wallet:IDR')->first();
        $this->assertEquals('0', (string) $escrowAcc->cached_balance);
        $this->assertEquals('0', (string) $importerAcc->cached_balance);
    }

    public function test_83_3_and_83_4_cbam_carbon_emission_certification_and_margin_impact(): void
    {
        // 25 tons steel, embedded emissions = 45.5 tCO2e, @ 80 EUR/ton, rate 17,000 IDR/EUR
        // Levy = 45.5 * 80 * 17,000 = 61,880,000 IDR
        $cert = $this->service->issueCbamCertificate(
            containerNumber: 'MSCU-CONT-7711',
            originFactoryCode: 'STEEL-PLANT-CILEGON-01',
            commodityCode: 'STEEL',
            netMassTons: 25.0,
            embeddedEmissionsTco2: 45.5,
            eurToIdrRate: 17_000,
            cbamPriceEurPerTon: 80,
            carbonCreditRef: 'ESG-CREDIT-OFFSET-99'
        );

        $this->assertEquals('CERTIFIED', $cert->status);
        $this->assertEquals(45.5, (float) $cert->embedded_emissions_tco2);
        $this->assertEquals(61_880_000, $cert->cbam_levy_cost_idr);
        $this->assertEquals('ESG-CREDIT-OFFSET-99', $cert->carbon_credit_ref);
        $this->assertNotNull($cert->certificate_hash);

        // Test export margin impact
        $baseExportMargin = 150_000_000; // 150 jt
        $impact = $this->service->calculateExportMarginImpact($cert, $baseExportMargin);
        $this->assertEquals(150_000_000, $impact['base_margin_idr']);
        $this->assertEquals(61_880_000, $impact['cbam_levy_deduction_idr']);
        $this->assertEquals(88_120_000, $impact['net_margin_idr']);
        $this->assertGreaterThan(40.0, $impact['margin_reduction_percent']);
    }

    public function test_83_5_escrow_and_cbam_summaries(): void
    {
        $this->service->depositEscrow(
            importerId: 101,
            exporterId: 202,
            amountCents: 100_000,
            lockedFxRateIdr: 16_000,
            blNumber: 'BL-SUMM-01',
            blDocument: 'DOC'
        );

        $this->service->issueCbamCertificate(
            containerNumber: 'CONT-SUMM-01',
            originFactoryCode: 'PLANT-01',
            commodityCode: 'ALUMINUM',
            netMassTons: 10.0,
            embeddedEmissionsTco2: 12.0
        );

        $escrowSummary = $this->service->getEscrowCorridorSummary();
        $this->assertGreaterThanOrEqual(1, $escrowSummary['total_escrows']);
        $this->assertGreaterThanOrEqual(16_000_000, $escrowSummary['active_liquidity_idr']);

        $cbamSummary = $this->service->getCbamComplianceSummary();
        $this->assertGreaterThanOrEqual(1, $cbamSummary['total_certificates']);
        $this->assertGreaterThanOrEqual(12.0, $cbamSummary['total_emissions_tco2']);
    }

    public function test_83_6_clearing_audit_command_enforces_zero_discrepancy(): void
    {
        $this->artisan('clearing:audit')->assertSuccessful();

        // Introduce discrepancy: released escrow without pod_chain_hash
        $badEscrow = CrossBorderEscrow::create([
            'escrow_code' => 'CBE-BAD-RELEASE',
            'importer_id' => 999,
            'exporter_id' => 888,
            'stablecoin_asset' => 'USDC',
            'amount_cents' => 100_000,
            'locked_fx_rate_idr' => 16_000,
            'bl_number' => 'BL-BAD-01',
            'bl_document_hash' => 'HASH-BAD',
            'pod_chain_hash' => null, // violation!
            'status' => 'RELEASED',
        ]);

        $this->artisan('clearing:audit')
            ->assertFailed()
            ->expectsOutputToContain('clearing.unverified_pod_release');

        $badEscrow->delete();
        $this->artisan('clearing:audit')->assertSuccessful();
    }

    public function test_83_http_endpoints_and_rbac(): void
    {
        // 1. Dashboard
        $res = $this->actingAs($this->admin)->get(route('trade_finance.crossborder.index'));
        $res->assertOk();
        $res->assertViewIs('trade_finance::crossborder');

        // 2. Deposit POST
        $postDeposit = $this->actingAs($this->treasury)->post(route('trade_finance.crossborder.deposit'), [
            'importer_id' => 301,
            'exporter_id' => 401,
            'amount_cents' => 300_000,
            'locked_fx_rate_idr' => 16_200,
            'bl_number' => 'BL-HTTP-TEST-01',
            'bl_document' => 'DOC_CONTENT_HTTP',
        ]);
        $postDeposit->assertRedirect();
        $escrow = CrossBorderEscrow::where('bl_number', 'BL-HTTP-TEST-01')->first();
        $this->assertNotNull($escrow);

        // 3. Dispute POST
        $postDispute = $this->actingAs($this->treasury)->post(route('trade_finance.crossborder.dispute', $escrow), [
            'reason' => 'Dispute via HTTP test',
        ]);
        $postDispute->assertRedirect();
        $this->assertEquals('DISPUTED', $escrow->fresh()->status);

        // 4. Resolve Dispute POST
        $postResolve = $this->actingAs($this->treasury)->post(route('trade_finance.crossborder.resolve-dispute', $escrow), [
            'decision' => 'PROCEED',
        ]);
        $postResolve->assertRedirect();
        $this->assertEquals('DEPOSITED', $escrow->fresh()->status);

        // 5. Release POST
        $postRelease = $this->actingAs($this->treasury)->post(route('trade_finance.crossborder.release', $escrow), [
            'pod_hash' => 'POD_HASH_HTTP_VERIFIED',
        ]);
        $postRelease->assertRedirect();
        $this->assertEquals('RELEASED', $escrow->fresh()->status);

        // 6. CBAM Certificate POST
        $postCbam = $this->actingAs($this->treasury)->post(route('trade_finance.crossborder.cbam'), [
            'container_number' => 'CONT-HTTP-EU-01',
            'origin_factory_code' => 'PLANT-CBT-01',
            'commodity_code' => 'FERTILIZER',
            'net_mass_tons' => 15.0,
            'embedded_emissions_tco2' => 22.5,
            'carbon_credit_ref' => 'ESG-CREDIT-7788',
        ]);
        $postCbam->assertRedirect();
        $this->assertDatabaseHas('tf_cbam_certificates', ['container_number' => 'CONT-HTTP-EU-01']);
    }
}
