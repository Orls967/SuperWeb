<?php

declare(strict_types=1);

namespace Modules\Core\tests\Feature;

use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Domain\Models\LedgerTransaction;
use Modules\Core\Application\Services\UniversalCrossLineFintechService;
use Modules\Core\Application\Services\UniversalStablecoinClearingService;
use Tests\TestCase;

class UniversalCrossLineFintechIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected UniversalStablecoinClearingService $stablecoinService;

    protected UniversalCrossLineFintechService $fintechService;

    protected Ledger $ledger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
        $this->stablecoinService = app(UniversalStablecoinClearingService::class);
        $this->fintechService = app(UniversalCrossLineFintechService::class);
        $this->ledger = app(Ledger::class);
    }

    public function test_stablecoin_24_7_cross_border_clearing_with_locked_fx(): void
    {
        // 1. Offtake contract between Mine Morowali and Smelter Singapore: 50,000 USDC @ Rp 16,000/USD
        $quoteTime = time();
        $result = $this->stablecoinService->clearCrossBorderContract(
            sourceEntityId: 'MINE_MOROWALI',
            beneficiaryEntityId: 'SMELTER_SINGAPORE',
            usdcAmountCents: 5_000_000, // 50,000.00 USDC
            lockedRateIdrPerUsd: 16_000,
            contractRef: 'OFFTAKE-NICKEL-2026-B88',
            quoteCreatedAtTimestamp: $quoteTime
        );

        $this->assertEquals('CLEARED_INSTANT', $result['status']);
        $this->assertEquals(5_000_000, $result['cleared_usdc']);
        $this->assertEquals(800_000_000, $result['equivalent_idr']); // 50,000 * 16,000 = Rp 800,000,000

        // 2. Verify ledger balanced double entry for both USDC and IDR assets
        $tx = LedgerTransaction::where('type', 'CROSS_LINE_STABLECOIN_SETTLE')->firstOrFail();

        $sumUsdc = 0;
        $sumIdr = 0;
        foreach ($tx->entries as $entry) {
            $val = (int) (string) $entry->amount;
            if ($entry->asset_code === 'USDC') {
                $sumUsdc += $val;
            } elseif ($entry->asset_code === 'IDR') {
                $sumIdr += $val;
            }
        }

        $this->assertSame(0, $sumUsdc, 'USDC clearing entries must balance to zero');
        $this->assertSame(0, $sumIdr, 'IDR intercompany settlement entries must balance to zero');
    }

    public function test_stablecoin_clearing_rejects_expired_fx_quote(): void
    {
        $expiredQuoteTime = time() - 950; // 950 seconds ago (> 15 minutes TTL)

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('EXPIRED_FX_QUOTE');

        $this->stablecoinService->clearCrossBorderContract(
            sourceEntityId: 'MINE_MOROWALI',
            beneficiaryEntityId: 'SMELTER_SINGAPORE',
            usdcAmountCents: 1_000_000,
            lockedRateIdrPerUsd: 16_000,
            contractRef: 'OFFTAKE-EXPIRED-TEST',
            quoteCreatedAtTimestamp: $expiredQuoteTime
        );
    }

    public function test_cross_line_bundle_settlement_and_three_line_insurance_autopilot(): void
    {
        // 1. Multi-vendor bundle payment: Rp 10.000.000
        // Settle: Hotel Rp 5.5M, Venue Rp 3M, Resto Rp 1M, Platform fee Rp 0.5M
        $bundlePaymentIdr = 10_000_000;
        $hotelShare = 5_500_000;
        $venueShare = 3_000_000;
        $restoShare = 1_000_000;
        $platformFee = 500_000;

        $this->ledger->post(new PostingDTO(
            type: 'settlement',
            description: 'Cross-line bundle settlement (Hotel + Venue + Resto) with platform fee',
            idempotencyKey: 'bundle_settle:PKG-BALI-ESCAPE-01',
            entries: [
                PostingEntryDTO::forCode('clearing:external:IDR', 'IDR', -$bundlePaymentIdr),
                PostingEntryDTO::forCode('htl:settlement_clearing:IDR', 'IDR', $hotelShare),
                PostingEntryDTO::forCode('ven:pos_revenue:IDR', 'IDR', $venueShare),
                PostingEntryDTO::forCode('revenue:group_conglomerate:IDR', 'IDR', $restoShare),
                PostingEntryDTO::forCode('platform:service_fee:IDR', 'IDR', $platformFee),
            ],
            referenceType: 'bundle_packages',
            referenceId: 'PKG-BALI-ESCAPE-01',
        ));

        // 2. Parametric claims autopilot across 3 lines
        $claimLogistics = $this->fintechService->triggerUniversalClaimPayout(
            lineDomain: 'LOGISTICS',
            referenceEntityId: 'CL-LOGISTICS-01',
            policyNumber: 'POL-REEFER-001',
            claimAmountIdr: 15_000_000,
            reason: 'Reefer temperature exceeded 8C'
        );
        $this->assertEquals('disbursed', $claimLogistics['status']);

        $claimMining = $this->fintechService->triggerUniversalClaimPayout(
            lineDomain: 'MINING',
            referenceEntityId: 'CL-MINING-02',
            policyNumber: 'POL-MINE-WEATHER-99',
            claimAmountIdr: 50_000_000,
            reason: 'Severe pit inundation'
        );
        $this->assertEquals('disbursed', $claimMining['status']);

        $claimHotel = $this->fintechService->triggerUniversalClaimPayout(
            lineDomain: 'HOTEL',
            referenceEntityId: 'CL-HOTEL-03',
            policyNumber: 'POL-FLIGHT-DELAY-55',
            claimAmountIdr: 5_000_000,
            reason: 'Flight delayed > 6 hours causing no-show'
        );
        $this->assertEquals('disbursed', $claimHotel['status']);

        // 3. Verify all transactions in ledger balance with 0 discrepancy
        $txs = LedgerTransaction::with('entries')->get();
        $this->assertGreaterThanOrEqual(4, $txs->count());

        foreach ($txs as $tx) {
            $sumByAsset = [];
            foreach ($tx->entries as $entry) {
                $asset = $entry->asset_code;
                $sumByAsset[$asset] = ($sumByAsset[$asset] ?? 0) + (int) (string) $entry->amount;
            }
            foreach ($sumByAsset as $asset => $sum) {
                $this->assertSame(0, $sum, "Transaction {$tx->id} must balance to zero for asset {$asset}");
            }
        }
    }
}
