<?php

declare(strict_types=1);

namespace Tests\Unit\Algorithms;

use Carbon\CarbonImmutable;
use Modules\Shared\Domain\Algorithms\Aviation\AirworthinessCertificate;
use Modules\Shared\Domain\Algorithms\Aviation\AirworthinessCertificateVerifier;
use Modules\Shared\Domain\Algorithms\Aviation\AirworthinessDispatchGate;
use Modules\Shared\Domain\Algorithms\Aviation\AirworthinessResult;
use Modules\Shared\Domain\Algorithms\Aviation\DangerousGoodsCertificate;
use Modules\Shared\Domain\Algorithms\Aviation\DangerousGoodsCertificateVerifier;
use Modules\Shared\Domain\Algorithms\Aviation\DangerousGoodsEligibilityChecker;
use Modules\Shared\Domain\Algorithms\Aviation\DangerousGoodsResult;
use Modules\Shared\Domain\Algorithms\Aviation\FareRefundCalculator;
use Modules\Shared\Domain\Algorithms\Aviation\FareRefundCertificate;
use Modules\Shared\Domain\Algorithms\Aviation\FareRefundCertificateVerifier;
use Modules\Shared\Domain\Algorithms\Aviation\FareRefundResult;
use Modules\Shared\Domain\Algorithms\Aviation\LoadAndBalanceCertificate;
use Modules\Shared\Domain\Algorithms\Aviation\LoadAndBalanceCertificateVerifier;
use Modules\Shared\Domain\Algorithms\Aviation\LoadAndBalanceChecker;
use Modules\Shared\Domain\Algorithms\Aviation\LoadAndBalanceResult;
use Modules\Shared\Domain\Algorithms\Aviation\SlotCoordinationAllocator;
use Modules\Shared\Domain\Algorithms\Aviation\SlotCoordinationCertificate;
use Modules\Shared\Domain\Algorithms\Aviation\SlotCoordinationCertificateVerifier;
use Modules\Shared\Domain\Algorithms\Aviation\SlotCoordinationResult;
use Modules\Shared\Domain\Math\Exceptions\InvalidCertificateException;
use PHPUnit\Framework\TestCase;

class AviationAlgorithmsTest extends TestCase
{
    /**
     * Vektor A1: SlotCoordinationAllocator.
     * Vektor prompt:
     * kapasitas 10;
     * historis A 4 slot terpakai 90%, B 3 slot 80%, C 2 slot 79%;
     * permintaan N1 (baru, 2, t=1), A (lama, 1, t=2), D (lama, 2, t=3), N2 (baru, 1, t=4):
     * dipertahankan A=4, B=3; C dikembalikan; kolam 3; cadangan 2;
     * hasil N1=2, A=+1, D=0, N2=0 (waitlist); sisa 0.
     */
    public function test_vektor_a1_slot_coordination_allocator(): void
    {
        $allocator = new SlotCoordinationAllocator;

        $historical = [
            ['operator' => 'A', 'held' => 4, 'usage_bps' => 9000],
            ['operator' => 'B', 'held' => 3, 'usage_bps' => 8000],
            ['operator' => 'C', 'held' => 2, 'usage_bps' => 7900],
        ];

        $requests = [
            ['operator' => 'N1', 'requested' => 2, 'is_new_entrant' => true, 'request_time' => 1],
            ['operator' => 'A', 'requested' => 1, 'is_new_entrant' => false, 'request_time' => 2],
            ['operator' => 'D', 'requested' => 2, 'is_new_entrant' => false, 'request_time' => 3],
            ['operator' => 'N2', 'requested' => 1, 'is_new_entrant' => true, 'request_time' => 4],
        ];

        $res = $allocator->allocate(10, $historical, $requests);

        // Retained: A=4, B=3, C=0
        $this->assertEquals(4, $res->retained['A']);
        $this->assertEquals(3, $res->retained['B']);
        $this->assertEquals(0, $res->retained['C']);

        // Hasil alokasi permintaan: N1=2, A=1, D=0, N2=0 (waitlist)
        $this->assertEquals(2, $res->allocated['N1']);
        $this->assertEquals(1, $res->allocated['A']);
        $this->assertEquals(0, $res->allocated['D']);
        $this->assertEquals(0, $res->allocated['N2']);

        // Waitlist
        $this->assertEquals(2, $res->waitlisted['D']);
        $this->assertEquals(1, $res->waitlisted['N2']);

        // Sisa kolam: 0
        $this->assertEquals(0, $res->remainingPool);

        // Sertifikat rusak memicu exception
        $this->expectException(InvalidCertificateException::class);
        $tamperedCert = new SlotCoordinationCertificate(
            capacity: 5, // kapasitas dirusak
            totalRetained: $res->certificate->totalRetained,
            pool: $res->certificate->pool,
            allocated: $res->allocated,
            waitlisted: $res->waitlisted,
            remainingPool: $res->remainingPool
        );
        $tamperedResult = new SlotCoordinationResult(
            retained: $res->retained,
            allocated: $res->allocated,
            waitlisted: $res->waitlisted,
            remainingPool: $res->remainingPool,
            certificate: $tamperedCert
        );
        SlotCoordinationCertificateVerifier::verify($tamperedResult);
    }

    /**
     * Vektor A2: AirworthinessDispatchGate.
     * Vektor prompt:
     * hari 2026-11-08; A-check batas 36.000 menit, berjalan 35.910; siklus 20.000/19.990:
     * - terbang 60 menit, sertifikat 2026-12-31, MEL 2026-11-10 → izin, margin 90 menit;
     * - terbang 120 menit → tolak [A_CHECK_HOURS];
     * - MEL 2026-11-07 → tolak [MEL_EXPIRED];
     * - sertifikat 2026-11-07 + MEL 2026-11-07 + terbang 120 → tolak [A_CHECK_HOURS, CERT_EXPIRED, MEL_EXPIRED].
     */
    public function test_vektor_a2_airworthiness_dispatch_gate(): void
    {
        $gate = new AirworthinessDispatchGate;
        $today = CarbonImmutable::parse('2026-11-08');

        // Case 1: Izin, margin 90 menit
        $res1 = $gate->evaluate(
            evalDate: $today,
            certExpiry: CarbonImmutable::parse('2026-12-31'),
            aCheckDueMinutes: 36000,
            aCheckCurrentMinutes: 35910,
            cyclesLimit: 20000,
            cyclesCurrent: 19990,
            flightDurationMinutes: 60,
            flightCycles: 1,
            melDeferralExpiry: CarbonImmutable::parse('2026-11-10')
        );
        $this->assertTrue($res1->isPermitted);
        $this->assertEquals(90, $res1->hoursMargin);
        $this->assertEmpty($res1->reasons);

        // Case 2: Tolak A_CHECK_HOURS
        $res2 = $gate->evaluate(
            evalDate: $today,
            certExpiry: CarbonImmutable::parse('2026-12-31'),
            aCheckDueMinutes: 36000,
            aCheckCurrentMinutes: 35910,
            cyclesLimit: 20000,
            cyclesCurrent: 19990,
            flightDurationMinutes: 120,
            flightCycles: 1,
            melDeferralExpiry: CarbonImmutable::parse('2026-11-10')
        );
        $this->assertFalse($res2->isPermitted);
        $this->assertEquals(['A_CHECK_HOURS'], $res2->reasons);

        // Case 3: Tolak MEL_EXPIRED
        $res3 = $gate->evaluate(
            evalDate: $today,
            certExpiry: CarbonImmutable::parse('2026-12-31'),
            aCheckDueMinutes: 36000,
            aCheckCurrentMinutes: 35910,
            cyclesLimit: 20000,
            cyclesCurrent: 19990,
            flightDurationMinutes: 60,
            flightCycles: 1,
            melDeferralExpiry: CarbonImmutable::parse('2026-11-07')
        );
        $this->assertFalse($res3->isPermitted);
        $this->assertEquals(['MEL_EXPIRED'], $res3->reasons);

        // Case 4: Tolak multi-alasan
        $res4 = $gate->evaluate(
            evalDate: $today,
            certExpiry: CarbonImmutable::parse('2026-11-07'),
            aCheckDueMinutes: 36000,
            aCheckCurrentMinutes: 35910,
            cyclesLimit: 20000,
            cyclesCurrent: 19990,
            flightDurationMinutes: 120,
            flightCycles: 1,
            melDeferralExpiry: CarbonImmutable::parse('2026-11-07')
        );
        $this->assertFalse($res4->isPermitted);
        $this->assertEquals(['A_CHECK_HOURS', 'CERT_EXPIRED', 'MEL_EXPIRED'], $res4->reasons);

        // Sertifikat rusak memicu exception
        $this->expectException(InvalidCertificateException::class);
        $tamperedCert = new AirworthinessCertificate(
            evalDate: '2026-11-08',
            isPermitted: true, // dipalsukan izin true padahal ada reasons
            reasons: ['A_CHECK_HOURS'],
            hoursMargin: 90,
            cyclesMargin: 10
        );
        $tamperedResult = new AirworthinessResult(
            isPermitted: true,
            reasons: ['A_CHECK_HOURS'],
            hoursMargin: 90,
            cyclesMargin: 10,
            certificate: $tamperedCert
        );
        AirworthinessCertificateVerifier::verify($tamperedResult);
    }

    /**
     * Vektor A3: LoadAndBalanceChecker.
     * Vektor prompt:
     * MTOW 23.000 kg; CG 8.600–9.600 mm; bahan bakar 3.000 kg @ 9.200 mm;
     * OEW 13.000 @ 9.000; kru 180 @ 2.500;
     * penumpang 20×84 @ 5.500, 24×84 @ 9.000, 10×84 @ 12.500;
     * kargo depan 300 @ 4.000, belakang 400 @ 13.500:
     * - dasar → berat 21.416, momen 189.534.000, CG 8.850 → lolos;
     * - tanpa penumpang belakang, kargo belakang 0, kargo depan 1.500 → berat 21.376, CG 8.347 → tolak [CG_FWD_LIMIT];
     * - bahan bakar 6.000 → berat 24.416, CG 8.893 → tolak [MTOW_EXCEEDED].
     */
    public function test_vektor_a3_load_and_balance_checker(): void
    {
        $checker = new LoadAndBalanceChecker;

        $baseStations = [
            ['name' => 'OEW', 'weight_kg' => 13000, 'arm_mm' => 9000],
            ['name' => 'Crew', 'weight_kg' => 180, 'arm_mm' => 2500],
            ['name' => 'Pax Fwd', 'weight_kg' => 20 * 84, 'arm_mm' => 5500],
            ['name' => 'Pax Mid', 'weight_kg' => 24 * 84, 'arm_mm' => 9000],
            ['name' => 'Pax Aft', 'weight_kg' => 10 * 84, 'arm_mm' => 12500],
            ['name' => 'Cargo Fwd', 'weight_kg' => 300, 'arm_mm' => 4000],
            ['name' => 'Cargo Aft', 'weight_kg' => 400, 'arm_mm' => 13500],
        ];

        // Case 1: Dasar
        $res1 = $checker->check(
            mtowKg: 23000,
            cgForwardLimitMm: 8600,
            cgAftLimitMm: 9600,
            fuelWeightKg: 3000,
            fuelArmMm: 9200,
            stations: $baseStations
        );
        $this->assertEquals(21416, $res1->totalWeightKg);
        $this->assertEquals(189534000, $res1->totalMoment);
        $this->assertEquals(8850, $res1->cgMm);
        $this->assertTrue($res1->isWithinEnvelope);
        $this->assertEmpty($res1->violations);

        // Case 2: Tanpa pax belakang, kargo belakang 0, kargo depan 1500
        $case2Stations = [
            ['name' => 'OEW', 'weight_kg' => 13000, 'arm_mm' => 9000],
            ['name' => 'Crew', 'weight_kg' => 180, 'arm_mm' => 2500],
            ['name' => 'Pax Fwd', 'weight_kg' => 20 * 84, 'arm_mm' => 5500],
            ['name' => 'Pax Mid', 'weight_kg' => 24 * 84, 'arm_mm' => 9000],
            ['name' => 'Pax Aft', 'weight_kg' => 0, 'arm_mm' => 12500],
            ['name' => 'Cargo Fwd', 'weight_kg' => 1500, 'arm_mm' => 4000],
            ['name' => 'Cargo Aft', 'weight_kg' => 0, 'arm_mm' => 13500],
        ];
        $res2 = $checker->check(
            mtowKg: 23000,
            cgForwardLimitMm: 8600,
            cgAftLimitMm: 9600,
            fuelWeightKg: 3000,
            fuelArmMm: 9200,
            stations: $case2Stations
        );
        $this->assertEquals(21376, $res2->totalWeightKg);
        $this->assertEquals(8347, $res2->cgMm);
        $this->assertFalse($res2->isWithinEnvelope);
        $this->assertEquals(['CG_FWD_LIMIT'], $res2->violations);

        // Case 3: Bahan bakar 6000 kg
        $res3 = $checker->check(
            mtowKg: 23000,
            cgForwardLimitMm: 8600,
            cgAftLimitMm: 9600,
            fuelWeightKg: 6000,
            fuelArmMm: 9200,
            stations: $baseStations
        );
        $this->assertEquals(24416, $res3->totalWeightKg);
        $this->assertEquals(8893, $res3->cgMm);
        $this->assertFalse($res3->isWithinEnvelope);
        $this->assertEquals(['MTOW_EXCEEDED'], $res3->violations);

        // Sertifikat rusak memicu exception
        $this->expectException(InvalidCertificateException::class);
        $tamperedCert = new LoadAndBalanceCertificate(
            mtowKg: 23000,
            totalWeightKg: 21416,
            totalMoment: 189534000,
            cgMm: 9999, // CG dipalsukan tidak sesuai momen/berat
            cgForwardLimitMm: 8600,
            cgAftLimitMm: 9600,
            isWithinEnvelope: true,
            violations: []
        );
        $tamperedResult = new LoadAndBalanceResult(
            totalWeightKg: 21416,
            totalMoment: 189534000,
            cgMm: 9999,
            isWithinEnvelope: true,
            violations: [],
            certificate: $tamperedCert
        );
        LoadAndBalanceCertificateVerifier::verify($tamperedResult);
    }

    /**
     * Vektor A4: FareRefundCalculator.
     * Vektor prompt:
     * - STD, dasar 1.500.000, pajak 250.000, 100 jam → 1.350.000;
     * - STD 30 jam → 1.050.000;
     * - PROMO 10 jam → 250.000;
     * - FLEX no-show, dasar 2.000.000, pajak 300.000 → 1.200.000;
     * - dibatalkan maskapai, dasar 900.000, pajak 100.000 → 1.000.000.
     */
    public function test_vektor_a4_fare_refund_calculator(): void
    {
        $calc = new FareRefundCalculator;

        // 1. STD 100 jam
        $res1 = $calc->calculate('STD', 1500000, 250000, 100);
        $this->assertEquals(1350000, $res1->totalRefundMinor);
        $this->assertEquals(1100000, $res1->baseRefundMinor);
        $this->assertEquals(250000, $res1->taxRefundMinor);

        // 2. STD 30 jam
        $res2 = $calc->calculate('STD', 1500000, 250000, 30);
        $this->assertEquals(1050000, $res2->totalRefundMinor);
        $this->assertEquals(800000, $res2->baseRefundMinor);
        $this->assertEquals(250000, $res2->taxRefundMinor);

        // 3. PROMO 10 jam
        $res3 = $calc->calculate('PROMO', 1500000, 250000, 10);
        $this->assertEquals(250000, $res3->totalRefundMinor);
        $this->assertEquals(0, $res3->baseRefundMinor);
        $this->assertEquals(250000, $res3->taxRefundMinor);

        // 4. FLEX no-show
        $res4 = $calc->calculate('FLEX', 2000000, 300000, null);
        $this->assertEquals(1200000, $res4->totalRefundMinor);
        $this->assertEquals(900000, $res4->baseRefundMinor);
        $this->assertEquals(300000, $res4->taxRefundMinor);

        // 5. Dibatalkan maskapai
        $res5 = $calc->calculate('STD', 900000, 100000, 5, true);
        $this->assertEquals(1000000, $res5->totalRefundMinor);
        $this->assertEquals(900000, $res5->baseRefundMinor);
        $this->assertEquals(100000, $res5->taxRefundMinor);

        // Sertifikat rusak memicu exception
        $this->expectException(InvalidCertificateException::class);
        $tamperedCert = new FareRefundCertificate(
            fareClass: 'STD',
            baseFareMinor: 1500000,
            taxMinor: 250000,
            hoursBeforeFlight: 100,
            isCancelledByAirline: false,
            refundPercentageBps: 8000,
            baseRefundMinor: 1100000,
            adminFeeMinor: 100000,
            taxRefundMinor: 250000,
            totalRefundMinor: 9999999 // total refund dipalsukan tidak cocok
        );
        $tamperedResult = new FareRefundResult(
            baseRefundMinor: 1100000,
            adminFeeMinor: 100000,
            taxRefundMinor: 250000,
            totalRefundMinor: 9999999,
            refundPercentageBps: 8000,
            certificate: $tamperedCert
        );
        FareRefundCertificateVerifier::verify($tamperedResult);
    }

    /**
     * Vektor A5: DangerousGoodsEligibilityChecker.
     * Vektor prompt:
     * - UN1993 PAX 4.000 g → izin; 6.000 g → tolak [QTY_OVER_LIMIT];
     * - UN3480 PAX → tolak [MODE_FORBIDDEN]; UN3480 CAO 30.000 g → izin;
     * - UN1845 CAO 150.000 g, sektor 180 menit, daya tahan 300 → izin; daya tahan 299 → tolak [HOLDOVER_SHORT].
     */
    public function test_vektor_a5_dangerous_goods_eligibility_checker(): void
    {
        $checker = new DangerousGoodsEligibilityChecker;

        // 1. UN1993 PAX 4000 g -> Izin
        $res1 = $checker->check('UN1993', 'PAX', 4000);
        $this->assertTrue($res1->isEligible);
        $this->assertEmpty($res1->violations);

        // 2. UN1993 PAX 6000 g -> Tolak QTY_OVER_LIMIT
        $res2 = $checker->check('UN1993', 'PAX', 6000);
        $this->assertFalse($res1->violations === $res2->violations);
        $this->assertEquals(['QTY_OVER_LIMIT'], $res2->violations);

        // 3. UN3480 PAX -> Tolak MODE_FORBIDDEN
        $res3 = $checker->check('UN3480', 'PAX', 10000);
        $this->assertFalse($res3->isEligible);
        $this->assertEquals(['MODE_FORBIDDEN'], $res3->violations);

        // 4. UN3480 CAO 30.000 g -> Izin
        $res4 = $checker->check('UN3480', 'CAO', 30000);
        $this->assertTrue($res4->isEligible);
        $this->assertEmpty($res4->violations);

        // 5. UN1845 CAO 150.000 g, sektor 180 m, daya tahan 300 -> Izin
        $res5 = $checker->check('UN1845', 'CAO', 150000, 180, 300);
        $this->assertTrue($res5->isEligible);
        $this->assertEmpty($res5->violations);

        // 6. UN1845 CAO 150.000 g, sektor 180 m, daya tahan 299 -> Tolak HOLDOVER_SHORT
        $res6 = $checker->check('UN1845', 'CAO', 150000, 180, 299);
        $this->assertFalse($res6->isEligible);
        $this->assertEquals(['HOLDOVER_SHORT'], $res6->violations);

        // Sertifikat rusak memicu exception
        $this->expectException(InvalidCertificateException::class);
        $tamperedCert = new DangerousGoodsCertificate(
            unNumber: 'UN1993',
            mode: 'PAX',
            weightGrams: 6000,
            sectorDurationMinutes: null,
            uldHoldoverMinutes: null,
            isEligible: true, // dipalsukan true padahal ada violation
            violations: ['QTY_OVER_LIMIT']
        );
        $tamperedResult = new DangerousGoodsResult(
            isEligible: true,
            violations: ['QTY_OVER_LIMIT'],
            certificate: $tamperedCert
        );
        DangerousGoodsCertificateVerifier::verify($tamperedResult);
    }
}
