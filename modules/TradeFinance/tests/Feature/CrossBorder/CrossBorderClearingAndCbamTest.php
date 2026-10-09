<?php

namespace Modules\TradeFinance\tests\Feature\CrossBorder;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\TradeFinance\Application\Services\CrossBorder\CrossBorderClearingService;
use Tests\TestCase;

class CrossBorderClearingAndCbamTest extends TestCase
{
    use RefreshDatabase;

    protected CrossBorderClearingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CrossBorderClearingService::class);

        LedgerAccount::create([
            'code' => 'tf:crossborder_escrow:IDR',
            'name' => 'Cross-Border Escrow Holding',
            'asset_code' => 'IDR',
            'kind' => 'liability',
            'allow_negative' => true,
            'cached_balance' => '0',
        ]);

        LedgerAccount::create([
            'code' => 'tf:importer_wallet:IDR',
            'name' => 'Importer Wallet Account',
            'asset_code' => 'IDR',
            'kind' => 'liability',
            'allow_negative' => true,
            'cached_balance' => '0',
        ]);

        LedgerAccount::create([
            'code' => 'tf:exporter_settlement:IDR',
            'name' => 'Exporter Settlement Account',
            'asset_code' => 'IDR',
            'kind' => 'asset',
            'allow_negative' => true,
            'cached_balance' => '0',
        ]);
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

    public function test_83_3_and_83_4_cbam_carbon_emission_certification_and_levy_cost(): void
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
            cbamPriceEurPerTon: 80.0
        );

        $this->assertEquals('CERTIFIED', $cert->status);
        $this->assertEquals(45.5, (float) $cert->embedded_emissions_tco2);
        $this->assertEquals(61_880_000, $cert->cbam_levy_cost_idr);
        $this->assertNotNull($cert->certificate_hash);
    }
}
