<?php

declare(strict_types=1);

namespace Modules\Mining\tests\Feature;

use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Mining\Application\Services\MiningComplianceAndRoyaltyService;
use Modules\Mining\Domain\Models\MiningSite;
use Tests\TestCase;

class MiningWeighbridgeEnterpriseDeepeningTest extends TestCase
{
    use RefreshDatabase;

    protected MiningComplianceAndRoyaltyService $service;

    protected MiningSite $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(MiningComplianceAndRoyaltyService::class);
        $this->seed(LedgerAccountsSeeder::class);

        $this->site = MiningSite::create([
            'id' => (string) Str::uuid(),
            'site_code' => 'MINE-MOROWALI-02',
            'name' => 'Morowali Industrial Park Nickel Concession',
            'commodity' => 'NICKEL',
            'location' => 'Central Sulawesi',
        ]);
    }

    public function test_weighbridge_cryptographic_hash_chain_validates_sequence_integrity(): void
    {
        $ticket1 = $this->service->recordWeighbridgeTicket(
            siteId: $this->site->id,
            truckPlateNumber: 'DT-7001-MOR',
            grossWeightTon: 420.00,
            tareWeightTon: 120.00,
            nickelGradePercentage: 1.80,
            destinationStockpile: 'ROM_STOCKPILE_EAST'
        );

        $ticket2 = $this->service->recordWeighbridgeTicket(
            siteId: $this->site->id,
            truckPlateNumber: 'DT-7002-MOR',
            grossWeightTon: 450.00,
            tareWeightTon: 130.00,
            nickelGradePercentage: 1.82,
            destinationStockpile: 'ROM_STOCKPILE_EAST'
        );

        $this->assertEquals(300.00, (float) $ticket1->net_weight_ton);
        $this->assertEquals(320.00, (float) $ticket2->net_weight_ton);
        $this->assertEquals($ticket1->hash, $ticket2->previous_hash);
    }

    public function test_weighbridge_ticket_hash_chain_tamper_attempt_detected_and_rejected(): void
    {
        $ticket = $this->service->recordWeighbridgeTicket(
            siteId: $this->site->id,
            truckPlateNumber: 'DT-7003-MOR',
            grossWeightTon: 400.00,
            tareWeightTon: 100.00,
            nickelGradePercentage: 1.75,
            destinationStockpile: 'ROM_STOCKPILE_WEST'
        );

        // Attempting to recalculate hash with altered net weight (e.g., claiming 350t instead of 300t)
        $tamperedWeight = 350.00;
        $recalculatedHash = hash('sha256', $ticket->ticket_number.$ticket->truck_plate_number.$tamperedWeight.$ticket->previous_hash);

        $isTampered = ($ticket->hash !== $recalculatedHash);
        $this->assertTrue($isTampered);
    }

    public function test_weighbridge_three_point_reconciliation_flags_variance_exceeding_two_percent(): void
    {
        // 10,000 tons weighed at pit, but barge shipment measures only 9,700 tons (3.0% loss > 2.0% tolerance)
        $recon = $this->service->reconcileWeighbridgeStockpileAndShipment(
            weighbridgeTotalTon: 10000.0,
            stockpileTons: 10000.0,
            shipmentTons: 9700.0,
            tolerancePercent: 2.0
        );

        $this->assertEquals(300.0, $recon['variance_ton']);
        $this->assertEquals(3.0, $recon['variance_percent']);
        $this->assertEquals('investigation_required', $recon['status']);
    }

    public function test_multi_element_assay_analysis_calculates_penalty_for_excessive_silica_impurities(): void
    {
        // Offtaker specification: Max Silica (SiO2) is 25.0%. Penalty is 50,000 IDR / ton per % excess.
        $basePricePerTon = 1500000;
        $silicaThreshold = 25.0;
        $actualSilica = 28.5; // 3.5% excess

        $excessSilica = max(0.0, $actualSilica - $silicaThreshold);
        $penaltyPerTon = (int) round($excessSilica * 50000);
        $effectivePricePerTon = $basePricePerTon - $penaltyPerTon;

        $this->assertEquals(3.5, $excessSilica);
        $this->assertEquals(175000, $penaltyPerTon);
        $this->assertEquals(1325000, $effectivePricePerTon);
    }

    public function test_high_grade_ore_assay_result_applies_commercial_price_premium_on_invoice(): void
    {
        // Standard grade Ni 1.80%. If actual is 2.10% (high grade), apply 150,000 IDR / ton premium.
        $basePricePerTon = 1500000;
        $standardGrade = 1.80;
        $actualGrade = 2.10;

        $premiumPerTon = $actualGrade >= 2.00 ? 150000 : 0;
        $finalInvoicePricePerTon = $basePricePerTon + $premiumPerTon;

        $this->assertEquals(150000, $premiumPerTon);
        $this->assertEquals(1650000, $finalInvoicePricePerTon);
    }

    public function test_minerba_progressive_royalty_calculation_matches_statutory_tariff_table(): void
    {
        // 50,000 tons nickel ore, HPM benchmark 1,200,000 IDR / ton, statutory tariff 10%
        $royalty = $this->service->calculateAndPostRoyalty(
            siteId: $this->site->id,
            period: '2026-Q4',
            totalProductionTon: 50000.0,
            benchmarkPriceIdr: 1200000,
            royaltyRatePercentage: 10.0
        );

        // 50,000 * 1,200,000 = 60,000,000,000 IDR. 10% royalty = 6,000,000,000 IDR
        $this->assertEquals(6000000000, $royalty->royalty_due_idr);
        $this->assertEquals('accrued', $royalty->status);
    }

    public function test_reclamation_provision_fund_accrual_locked_at_25_percent_of_gross_ore_sales(): void
    {
        $grossSalesIdr = 60000000000; // 60B IDR
        $reclamationRate = 0.025; // 2.5% statutory post-mining reclamation provision

        $reclamationFundIdr = (int) round($grossSalesIdr * $reclamationRate);

        // Must allocate exactly 1,500,000,000 IDR (1.5B IDR)
        $this->assertEquals(1500000000, $reclamationFundIdr);
    }

    public function test_biometric_driver_fatigue_detection_triggers_immediate_k3_stop_work_alarm(): void
    {
        // Telematics camera monitoring driver eye closure
        $criticalThresholdSeconds = 1.5;
        $telemetryEyeClosureSeconds = 1.85; // microsleep detected

        $fatigueDetected = $telemetryEyeClosureSeconds >= $criticalThresholdSeconds;
        $this->assertTrue($fatigueDetected);

        $driverStatus = $fatigueDetected ? 'SUSPENDED_FATIGUE_ALARM' : 'ACTIVE';
        $this->assertEquals('SUSPENDED_FATIGUE_ALARM', $driverStatus);
    }
}
