<?php

declare(strict_types=1);

namespace Tests\Acceptance\AviationYield;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Aviation\Domain\Algorithms\CodeshareMileageAccrualEngine;
use Modules\Aviation\Domain\Algorithms\DisruptedPassengerReaccommodator;
use Modules\Aviation\Domain\Algorithms\FareBucketYieldOptimizer;
use Modules\Aviation\Domain\Algorithms\PassengerCareObligationCalculator;
use Modules\Aviation\Domain\StateMachines\CodeshareAgreementStateMachine;
use Modules\Aviation\Domain\StateMachines\DisruptionCaseStateMachine;
use Modules\Aviation\Domain\StateMachines\ScheduleVersionStateMachine;
use Modules\Insurance\Contracts\ParametricTriggerRecorder;
use Tests\TestCase;

class AviationYieldAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * Skenario 1: Optimasi Fare Bucket Yield menggunakan Algoritma EMSR-b (A6).
     */
    public function test_fare_bucket_yield_optimization_emsr_b(): void
    {
        $optimizer = app(FareBucketYieldOptimizer::class);
        $classes = [
            ['code' => 'Y', 'fare' => 2000000, 'mean_demand' => 30, 'std_dev' => 8],
            ['code' => 'B', 'fare' => 1500000, 'mean_demand' => 45, 'std_dev' => 10],
            ['code' => 'M', 'fare' => 1200000, 'mean_demand' => 50, 'std_dev' => 12],
            ['code' => 'Q', 'fare' => 800000, 'mean_demand' => 60, 'std_dev' => 15],
        ];

        $result = $optimizer->calculateProtectionAndBookingLimits(180, $classes);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('buckets', $result);
        $this->assertArrayHasKey('certificate_hash', $result);
        $this->assertCount(4, $result['buckets']);

        // Pastikan nesting property berlaku: BL_Y >= BL_B >= BL_M >= BL_Q
        $this->assertEquals(180, $result['buckets'][0]['booking_limit']);
        $this->assertGreaterThanOrEqual($result['buckets'][1]['booking_limit'], $result['buckets'][0]['booking_limit']);
        $this->assertGreaterThanOrEqual($result['buckets'][2]['booking_limit'], $result['buckets'][1]['booking_limit']);
        $this->assertGreaterThanOrEqual($result['buckets'][3]['booking_limit'], $result['buckets'][2]['booking_limit']);
    }

    /**
     * Skenario 2: Codeshare Proration & Anti-Double-Dipping Frequent Flyer Accrual (A8).
     */
    public function test_codeshare_proration_and_anti_double_dip_loyalty(): void
    {
        $engine = app(CodeshareMileageAccrualEngine::class);

        // Kasus 1: Perhitungan Prorata Jarak dan Bagi Hasil
        $proration = $engine->calculateRevenueShareAndMiles(
            grossFareMinorUnit: 250000000, // Rp2.500.000
            tpmDistance: 612, // DPS-CGK ~612 miles
            cabinClass: 'economy_standard',
            passengerTier: 'gold',
            operatingAirlineBps: 8500, // 85%
            marketingAirlineBps: 1500  // 15%
        );

        $this->assertEquals(212500000, $proration['operating_share_minor_unit']);
        $this->assertEquals(37500000, $proration['marketing_share_minor_unit']);
        $this->assertEquals(918, $proration['miles_accrued']); // 612 * 1.0 * 1.5 (Gold +50%)

        // Kasus 2: Nonce Claim Composite Hash untuk Anti-Double Dip
        $claimHash1 = $engine->generateClaimNonceHash('1261234567890', 1, '2026-11-08');
        $claimHash2 = $engine->generateClaimNonceHash('1261234567890', 1, '2026-11-08');
        $claimHashOther = $engine->generateClaimNonceHash('1261234567890', 2, '2026-11-08');

        $this->assertEquals($claimHash1, $claimHash2);
        $this->assertNotEquals($claimHash1, $claimHashOther);
    }

    /**
     * Skenario 3: Penanganan Disrupsi Penerbangan PM 89/2015 & Hak Kompensasi Penumpang (A9).
     */
    public function test_passenger_care_obligation_pm89(): void
    {
        $calculator = app(PassengerCareObligationCalculator::class);

        // Kategori 1: Delay 45 menit -> Refreshment
        $evalCat1 = $calculator->evaluateObligation(45, 'AOG_TECHNICAL');
        $this->assertEquals('CAT1', $evalCat1['category']);
        $this->assertTrue($evalCat1['duty_of_care_required']);
        $this->assertFalse($evalCat1['cash_compensation_required']);
        $this->assertEquals('refreshment', $evalCat1['voucher_type']);

        // Kategori 3: Delay 150 menit -> Heavy Meal
        $evalCat3 = $calculator->evaluateObligation(150, 'AOG_TECHNICAL');
        $this->assertEquals('CAT3', $evalCat3['category']);
        $this->assertEquals('meal', $evalCat3['voucher_type']);

        // Kategori 5: Delay 270 menit karena Masalah Teknis -> Makanan + Tunai Rp300.000
        $evalCat5Tech = $calculator->evaluateObligation(270, 'AOG_TECHNICAL');
        $this->assertEquals('CAT5', $evalCat5Tech['category']);
        $this->assertTrue($evalCat5Tech['cash_compensation_required']);
        $this->assertEquals(30000000, $evalCat5Tech['cash_compensation_amount_minor_unit']); // Rp300.000

        // Kategori 5 Cuaca Buruk (Force Majeure) -> Duty of Care tetap berlaku, kompensasi tunai dikecualikan
        $evalCat5Weather = $calculator->evaluateObligation(270, 'FORCE_MAJEURE_WEATHER');
        $this->assertEquals('CAT5', $evalCat5Weather['category']);
        $this->assertTrue($evalCat5Weather['duty_of_care_required']);
        $this->assertFalse($evalCat5Weather['cash_compensation_required']);
    }

    /**
     * Skenario 4: Simulasi Krisis Erupsi Bandara DPS (Gunung Agung VONA Red) dengan Re-akomodasi (A7), Hotel & Asuransi Parametrik (G7-K29).
     */
    public function test_volcanic_eruption_irrops_crisis_simulation(): void
    {
        // 1. Setup mock / kontrak asuransi parametrik G7-K29
        $parametricRecorder = app(ParametricTriggerRecorder::class);
        $triggerId = $parametricRecorder->recordTrigger(
            policyNumber: 'POL-TRAVEL-2026-DPS-991',
            flightNumber: 'GA402',
            triggerType: 'VOLCANIC_ERUPTION_CLOSURE',
            durationMinutes: 1080,
            payoutAmountMinorUnit: 100000000, // Rp1.000.000
            telemetryData: ['vona_color' => 'RED', 'volcano' => 'Gunung Agung', 'ash_level' => 'FL320']
        );

        $this->assertNotEmpty($triggerId);

        // 2. Prioritas Re-akomodasi Penumpang A7
        $reaccommodator = app(DisruptedPassengerReaccommodator::class);
        $passengers = [
            ['id' => 1, 'name' => 'Budi Santoso', 'tier' => 'classic', 'cabin' => 'economy', 'has_connection' => false, 'special' => null, 'booking_time' => 1000],
            ['id' => 2, 'name' => 'Wayan Sukerta', 'tier' => 'platinum', 'cabin' => 'business', 'has_connection' => true, 'special' => null, 'booking_time' => 1200],
            ['id' => 3, 'name' => 'Anak Tanpa Pendamping (UM)', 'tier' => 'classic', 'cabin' => 'economy', 'has_connection' => false, 'special' => 'UMNR', 'booking_time' => 1100],
        ];

        $sortedQueue = $reaccommodator->prioritizePassengers($passengers);

        // Penumpang berkebutuhan khusus (UMNR) dan Platinum koneksi harus menduduki peringkat teratas
        $this->assertEquals(3, $sortedQueue[0]['id']); // UMNR skor tertinggi
        $this->assertEquals(2, $sortedQueue[1]['id']); // Platinum Business Connection
        $this->assertEquals(1, $sortedQueue[2]['id']); // Classic regular
    }

    /**
     * Skenario 5: Integritas State Machine Transisi Ilegal (ScheduleVersion, CodeshareAgreement, DisruptionCase).
     */
    public function test_state_machine_transitions_and_illegal_guards(): void
    {
        $schedSm = new ScheduleVersionStateMachine;
        $this->assertTrue($schedSm->canTransition('draft', 'review'));
        $this->assertFalse($schedSm->canTransition('draft', 'active')); // Harus lewat review & published

        $codeSm = new CodeshareAgreementStateMachine;
        $this->assertTrue($codeSm->canTransition('proposed', 'negotiating'));
        $this->assertTrue($codeSm->canTransition('signed', 'active'));
        $this->assertFalse($codeSm->canTransition('proposed', 'active')); // Tidak bisa loncat tanpa signed

        $disruptSm = new DisruptionCaseStateMachine;
        $this->assertTrue($disruptSm->canTransition('detected', 'classified'));
        $this->assertTrue($disruptSm->canTransition('care_dispatched', 'reaccommodating'));
        $this->assertFalse($disruptSm->canTransition('detected', 'closed')); // Tidak bisa langsung tutup tanpa penyelesaian
    }
}
