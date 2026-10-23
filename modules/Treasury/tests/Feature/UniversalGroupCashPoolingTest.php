<?php

declare(strict_types=1);

namespace Modules\Treasury\tests\Feature;

use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Domain\Models\LedgerTransaction;
use Modules\Treasury\Application\Services\UniversalGroupCashPoolingService;
use Tests\TestCase;

class UniversalGroupCashPoolingTest extends TestCase
{
    use RefreshDatabase;

    protected UniversalGroupCashPoolingService $poolingService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
        $this->poolingService = app(UniversalGroupCashPoolingService::class);
    }

    public function test_daily_idle_cash_sweep_to_treasury_yield_pool(): void
    {
        // Entity Hotel Bali has closing balance Rp 250M, operational float buffer Rp 50M -> Sweep Rp 200M
        $sweep = $this->poolingService->sweepEntityExcessCash(
            entityId: 'ENT-HOTEL-BALI',
            closingBalanceIdr: 250_000_000,
            operationalFloatTargetIdr: 50_000_000,
            dateYmd: '2026-10-22'
        );

        $this->assertEquals('SWEPT_TO_YIELD_POOL', $sweep['status']);
        $this->assertEquals(200_000_000, $sweep['swept_amount_idr']);
        $this->assertEquals(50_000_000, $sweep['remaining_buffer_idr']);

        // Verify ledger transaction
        $tx = LedgerTransaction::where('type', 'settlement')->firstOrFail();
        $sum = 0;
        foreach ($tx->entries as $entry) {
            $sum += (int) (string) $entry->amount;
        }
        $this->assertSame(0, $sum, 'Cash pooling sweep double-entry must sum to zero');

        // Entity with closing balance below buffer -> No sweep
        $noSweep = $this->poolingService->sweepEntityExcessCash(
            entityId: 'ENT-LOGISTICS-HUB',
            closingBalanceIdr: 30_000_000,
            operationalFloatTargetIdr: 50_000_000,
            dateYmd: '2026-10-22'
        );

        $this->assertEquals('NO_EXCESS_FLOAT', $noSweep['status']);
        $this->assertEquals(0, $noSweep['swept_amount_idr']);
        $this->assertEquals(30_000_000, $noSweep['remaining_buffer_idr']);
    }

    public function test_overnight_yield_calculation_and_pro_rata_distribution(): void
    {
        // 3 entities contributing total Rp 1.000.000.000 (Rp 1B)
        $contributions = [
            'ENT-HOTEL-BALI' => 500_000_000,     // 50%
            'ENT-MINING-KOLAKA' => 300_000_000,  // 30%
            'ENT-VENUE-JAKARTA' => 200_000_000,  // 20%
        ];

        // 5.50% annual yield on Rp 1B = Rp 55,000,000 / 365 = ~Rp 150,684 daily
        $yieldResult = $this->poolingService->calculateOvernightYieldDistribution(
            entityContributionMap: $contributions,
            annualYieldBps: 550
        );

        $this->assertEquals(1_000_000_000, $yieldResult['total_pool_idr']);
        $this->assertGreaterThan(0, $yieldResult['total_yield_idr']);

        $distributions = $yieldResult['distributions'];
        $this->assertArrayHasKey('ENT-HOTEL-BALI', $distributions);
        $this->assertArrayHasKey('ENT-MINING-KOLAKA', $distributions);
        $this->assertArrayHasKey('ENT-VENUE-JAKARTA', $distributions);

        // Hotel gets 50% of the daily yield
        $expectedHotelYield = (int) floor(($yieldResult['total_yield_idr'] * 500_000_000) / 1_000_000_000);
        $this->assertEquals($expectedHotelYield, $distributions['ENT-HOTEL-BALI']);
    }
}
