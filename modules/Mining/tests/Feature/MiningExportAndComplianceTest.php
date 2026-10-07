<?php

declare(strict_types=1);

namespace Modules\Mining\tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Banking\Domain\Models\LedgerTransaction;
use Modules\Mining\Application\Services\MiningExportAndComplianceService;
use RuntimeException;
use Tests\TestCase;

class MiningExportAndComplianceTest extends TestCase
{
    use RefreshDatabase;

    protected MiningExportAndComplianceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(MiningExportAndComplianceService::class);

        $accounts = [
            'min:trade_receivable:IDR' => 'asset',
            'min:demurrage_receivable:IDR' => 'asset',
            'min:demurrage_revenue:IDR' => 'revenue',
            'min:circular_economy_revenue:IDR' => 'revenue',
        ];

        foreach ($accounts as $code => $kind) {
            LedgerAccount::create([
                'code' => $code,
                'name' => "Mining {$code}",
                'asset_code' => 'IDR',
                'kind' => $kind,
                'allow_negative' => true,
                'cached_balance' => '0',
            ]);
        }
    }

    public function test_122_1_and_122_6_c_stockpile_inventory_reconciliation(): void
    {
        $stockpile = $this->service->createStockpile(
            'TERM-BALIKPAPAN-COAL',
            'STK-HIGH-CV-01',
            'THERMAL_COAL',
            100000.0 // 100k tons opening
        );

        $this->assertEquals(100000.0, $stockpile->opening_tonnage);
        $this->assertEquals(100000.0, $stockpile->remaining_tonnage);

        // Load 45,000 tons onto vessel
        $result = $this->service->loadVesselFromStockpile($stockpile->id, [
            'voyage_number' => 'VOY-PACIFIC-88',
            'vessel_name' => 'MV Pacific Bulk Pioneer',
            'buyer_party_id' => 'PARTY-TOKYO-ELEC',
            'destination_country' => 'JAPAN',
            'loaded_tonnage' => 45000.0,
            'contracted_tonnage' => 45000.0,
        ]);

        $updatedStockpile = $result['stockpile'];
        $this->assertEquals(45000.0, $updatedStockpile->loaded_tonnage);
        $this->assertEquals(55000.0, $updatedStockpile->remaining_tonnage);
        $this->assertEquals($updatedStockpile->opening_tonnage, $updatedStockpile->loaded_tonnage + $updatedStockpile->remaining_tonnage);
    }

    public function test_122_2_and_122_6_a_demurrage_calculation_and_ledger_entry(): void
    {
        $stockpile = $this->service->createStockpile('TERM-SAMARINDA', 'STK-CV-5800', 'COAL', 50000.0);
        $loadResult = $this->service->loadVesselFromStockpile($stockpile->id, [
            'voyage_number' => 'VOY-DEM-01',
            'vessel_name' => 'MV Bulk Carrier Alpha',
            'buyer_party_id' => 'PARTY-KOREA-POWER',
            'destination_country' => 'SOUTH_KOREA',
            'loaded_tonnage' => 30000.0,
            'allowed_laytime_hours' => 72.0,
            'demurrage_rate_per_hour_minor' => 10000000, // 10M IDR per hour
        ]);

        $voyage = $loadResult['voyage'];

        // Actual laytime 96 hours -> 24 hours excess * 10M IDR/hr = 240,000,000 IDR
        $finalized = $this->service->finalizeLaytimeAndDemurrage($voyage->id, 96.0);

        $this->assertEquals(240000000, $finalized->demurrage_total_minor);
        $this->assertEquals('DEPARTED', $finalized->status);

        // Check zero-sum ledger transaction
        $tx = LedgerTransaction::where('type', 'MINING_VESSEL_DEMURRAGE_CLAIM')->first();
        $this->assertNotNull($tx);
        $sum = $tx->entries->sum('amount_minor');
        $this->assertEquals(0, $sum);
    }

    public function test_122_3_and_122_6_b_assay_dispute_holds_payment(): void
    {
        $dispute = $this->service->reportCargoAssayDispute([
            'voyage_id' => 'VOY-DEM-01',
            'surveyor_party_id' => 'PARTY-SURVEYOR-SGS',
            'seller_grade_pct' => 1.80,
            'buyer_grade_pct' => 1.50, // 0.30% delta > 0.20% tolerance
            'grade_tolerance_pct' => 0.20,
        ]);

        $this->assertTrue($dispute->is_payment_held);
        $this->assertEquals('HELD', $dispute->status);
        $this->assertNotNull($dispute->sealed_sample_code);
    }

    public function test_122_4_fly_ash_circular_revenue(): void
    {
        $sale = $this->service->recordCircularFlyAshSale([
            'cement_buyer_party_id' => 'PARTY-CEMENT-INDOCEMENT',
            'fly_ash_tonnage' => 2000.0,
            'price_per_ton_minor' => 150000, // 150,000 IDR/ton -> 300,000,000 IDR total
        ]);

        $this->assertEquals(300000000, $sale->total_revenue_minor);
        $this->assertNotNull($sale->ledger_transaction_id);

        $tx = LedgerTransaction::with('entries')->findOrFail($sale->ledger_transaction_id);
        $sum = $tx->entries->sum('amount_minor');
        $this->assertEquals(0, $sum);
    }

    public function test_122_5_and_122_6_d_sanctions_destination_screening(): void
    {
        $stockpile = $this->service->createStockpile('TERM-TABONEO', 'STK-TAB-01', 'COAL', 40000.0);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('subject to active trade sanctions');

        $this->service->loadVesselFromStockpile($stockpile->id, [
            'voyage_number' => 'VOY-BLOCKED-01',
            'vessel_name' => 'MV Shadow Trader',
            'buyer_party_id' => 'PARTY-SANCTIONED',
            'destination_country' => 'NORTH_KOREA',
            'loaded_tonnage' => 10000.0,
        ]);
    }
}
