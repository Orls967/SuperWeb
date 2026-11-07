<?php

declare(strict_types=1);

namespace Tests\Unit\Algorithms;

use InvalidArgumentException;
use Modules\Hotel\Domain\Algorithms\BarRateCertificate;
use Modules\Hotel\Domain\Algorithms\BarRateCertificateVerifier;
use Modules\Hotel\Domain\Algorithms\BestAvailableRateCalculator;
use Modules\Hotel\Domain\Algorithms\HvacSetbackCertificate;
use Modules\Hotel\Domain\Algorithms\HvacSetbackCertificateVerifier;
use Modules\Hotel\Domain\Algorithms\HvacSetbackController;
use Modules\Hotel\Domain\Algorithms\OverbookingCertificate;
use Modules\Hotel\Domain\Algorithms\OverbookingCertificateVerifier;
use Modules\Hotel\Domain\Algorithms\OverbookingLimitOptimizer;
use Modules\Hotel\Domain\Algorithms\ServiceRecoveryCertificate;
use Modules\Hotel\Domain\Algorithms\ServiceRecoveryCertificateVerifier;
use Modules\Hotel\Domain\Algorithms\ServiceRecoveryCompensator;
use Modules\Shared\Domain\Algorithms\Routing\HousekeepingRouteCertificate;
use Modules\Shared\Domain\Algorithms\Routing\HousekeepingRouteCertificateVerifier;
use Modules\Shared\Domain\Algorithms\Routing\HousekeepingRouteSequencer;
use PHPUnit\Framework\TestCase;

final class HotelAlgorithmsFase91Test extends TestCase
{
    /**
     * Vektor Uji H1: OverbookingLimitOptimizer
     */
    public function test_h1_overbooking_limit_optimizer_prompt_vector(): void
    {
        $capacity = 100;
        $dist = [500, 1000, 2000, 2500, 2000, 1500, 500];
        $rev = 1_000_000;
        $walkCost = 2_500_000;

        $res = OverbookingLimitOptimizer::optimize(
            capacity: $capacity,
            noShowDistributionBps: $dist,
            revenuePerRoomIdr: $rev,
            walkCostIdr: $walkCost,
            policyMaxPercentBps: 300 // 3%
        );

        $expectedValues = [96_900_000, 97_725_000, 98_200_000, 97_975_000, 96_875_000, 95_075_000, 92_750_000];
        $this->assertSame($expectedValues, $res['values_by_k']);
        $this->assertSame(2, $res['optimal_k']);
        $this->assertSame(98_200_000, $res['expected_net_value_idr']);
        $this->assertEqualsWithDelta(0.2, $res['expected_walk_rooms'], 0.0001);

        // Sertifikat Rusak -> Exception
        $this->expectException(InvalidArgumentException::class);
        $badCert = new OverbookingCertificate(
            capacity: 100,
            optimalK: 5, // melebihi policy plafon 3
            expectedNetValueIdr: 98_200_000,
            expectedWalkRooms: 0.2,
            policyCeilingK: 3,
            valuesByK: $expectedValues,
            timestamp: time()
        );
        OverbookingCertificateVerifier::verify($badCert);
    }

    /**
     * Vektor Uji H2: BestAvailableRateCalculator
     */
    public function test_h2_best_available_rate_calculator_prompt_vector(): void
    {
        $floor = 450_000;
        $ceiling = 3_000_000;

        // Vektor 1: Dasar 1.200.000, okupansi 92%, lead 2, festival -> 1.883.000
        $r1 = BestAvailableRateCalculator::calculate(1_200_000, 0.92, 2, true, null, $floor, $ceiling);
        $this->assertSame(1_883_000, $r1['rate_idr']);

        // Vektor 2: Okupansi 40%, lead 45 -> 1.026.000
        $r2 = BestAvailableRateCalculator::calculate(1_200_000, 0.40, 45, false, null, $floor, $ceiling);
        $this->assertSame(1_026_000, $r2['rate_idr']);

        // Vektor 3: Okupansi 85%, lead 10 -> 1.380.000
        $r3 = BestAvailableRateCalculator::calculate(1_200_000, 0.85, 10, false, null, $floor, $ceiling);
        $this->assertSame(1_380_000, $r3['rate_idr']);

        // Vektor 4: Kanal korporat (kontrak 950.000) -> 950.000
        $r4 = BestAvailableRateCalculator::calculate(1_200_000, 0.85, 10, false, 950_000, $floor, $ceiling);
        $this->assertSame(950_000, $r4['rate_idr']);

        // Vektor 5: Dasar 300.000, okupansi 10%, lead 60 -> 450.000 (floor)
        $r5 = BestAvailableRateCalculator::calculate(300_000, 0.10, 60, false, null, $floor, $ceiling);
        $this->assertSame(450_000, $r5['rate_idr']);

        // Sertifikat Rusak -> Exception
        $this->expectException(InvalidArgumentException::class);
        $badCert = new BarRateCertificate(
            baseRateIdr: 1_200_000,
            finalRateIdr: 400_000, // di bawah floor 450.000
            floorRateIdr: $floor,
            ceilingRateIdr: $ceiling,
            isContractOverride: false,
            occupancyBps: 9000,
            leadBps: 10000,
            eventBps: 10000,
            timestamp: time()
        );
        BarRateCertificateVerifier::verify($badCert);
    }

    /**
     * Vektor Uji H3: HousekeepingRouteSequencer
     */
    public function test_h3_housekeeping_route_sequencer_prompt_vector(): void
    {
        $rooms = [
            ['room' => 101, 'credit' => 30, 'is_priority' => false, 'is_vip' => false],
            ['room' => 102, 'credit' => 20, 'is_priority' => false, 'is_vip' => false],
            ['room' => 103, 'credit' => 30, 'is_priority' => true, 'is_vip' => false],
            ['room' => 201, 'credit' => 20, 'is_priority' => false, 'is_vip' => false],
            ['room' => 202, 'credit' => 30, 'is_priority' => false, 'is_vip' => false],
            ['room' => 203, 'credit' => 40, 'is_priority' => true, 'is_vip' => true],
            ['room' => 204, 'credit' => 20, 'is_priority' => false, 'is_vip' => false],
            ['room' => 301, 'credit' => 30, 'is_priority' => false, 'is_vip' => false],
            ['room' => 302, 'credit' => 20, 'is_priority' => false, 'is_vip' => false],
        ];

        $res = HousekeepingRouteSequencer::sequence(
            rooms: $rooms,
            staffNames: ['A', 'B'],
            creditLimitPerStaff: 240
        );

        $this->assertSame([203, 202, 102, 201, 302], $res['assignments']['A']);
        $this->assertSame(130, $res['workloads']['A']);
        $this->assertSame([203, 102, 201, 202, 302], $res['routes']['A']);

        $this->assertSame([103, 101, 301, 204], $res['assignments']['B']);
        $this->assertSame(110, $res['workloads']['B']);
        $this->assertSame([103, 101, 204, 301], $res['routes']['B']);

        // Sertifikat Rusak -> Exception
        $this->expectException(InvalidArgumentException::class);
        $badCert = new HousekeepingRouteCertificate(
            assignments: ['A' => [101]],
            routes: ['A' => [101]],
            workloads: ['A' => 300], // overload > 240
            unassignedQueue: [],
            creditLimit: 240,
            timestamp: time()
        );
        HousekeepingRouteCertificateVerifier::verify($badCert);
    }

    /**
     * Vektor Uji H4: HvacSetbackController
     */
    public function test_h4_hvac_setback_controller_prompt_vector(): void
    {
        // Vektor: tamu keluar 10:00, setback 10:15, kembali 13:00 -> 165 menit setback, daya 1.200 W, faktor 3.000 bps -> 990 Wh
        $res = HvacSetbackController::evaluate(
            isOccupiedRoom: true,
            minutesSinceLastMotion: 180,
            durationMinutes: 180,
            acPowerWatt: 1200,
            savingFactorBps: 3000
        );

        $this->assertTrue($res['setback_active']);
        $this->assertSame(165, $res['setback_minutes']);
        $this->assertSame(990, $res['energy_saved_wh']);

        // Sertifikat Rusak -> Exception
        $this->expectException(InvalidArgumentException::class);
        $badCert = new HvacSetbackCertificate(
            isOccupiedRoom: true,
            setbackActive: false,
            setbackMinutes: 0,
            energySavedWh: 500, // invalid saving while inactive
            acPowerWatt: 1200,
            savingFactorBps: 3000,
            timestamp: time()
        );
        HvacSetbackCertificateVerifier::verify($badCert);
    }

    /**
     * Vektor Uji H5: ServiceRecoveryCompensator
     */
    public function test_h5_service_recovery_compensator_prompt_vector(): void
    {
        $rate = 1_500_000;

        // Vektor 1: lewat 1 jam -> 150.000, tanpa malam gratis
        $r1 = ServiceRecoveryCompensator::compensate($rate, 1.0);
        $this->assertSame(150_000, $r1['compensation_amount_idr']);
        $this->assertFalse($r1['grants_free_night']);

        // Vektor 2: lewat 4 jam -> 375.000, tanpa malam gratis
        $r2 = ServiceRecoveryCompensator::compensate($rate, 4.0);
        $this->assertSame(375_000, $r2['compensation_amount_idr']);
        $this->assertFalse($r2['grants_free_night']);

        // Vektor 3: lewat 10 jam -> 750.000 + malam gratis
        $r3 = ServiceRecoveryCompensator::compensate($rate, 10.0);
        $this->assertSame(750_000, $r3['compensation_amount_idr']);
        $this->assertTrue($r3['grants_free_night']);

        // Sertifikat Rusak -> Exception
        $this->expectException(InvalidArgumentException::class);
        $badCert = new ServiceRecoveryCertificate(
            nightRateIdr: 1_500_000,
            excessHours: 10.0,
            compensationAmountIdr: 750_000,
            compensationPercentBps: 5000,
            grantsFreeNight: false, // wajib true untuk > 6 jam
            timestamp: time()
        );
        ServiceRecoveryCertificateVerifier::verify($badCert);
    }
}
