<?php

declare(strict_types=1);

namespace Modules\Hospital\Tests\Unit;

use DateTimeImmutable;
use Modules\Hospital\Domain\Services\BloodBankHemovigilanceService;
use PHPUnit\Framework\TestCase;

class BloodBankHemovigilanceServiceTest extends TestCase
{
    private BloodBankHemovigilanceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BloodBankHemovigilanceService();
    }

    public function test_compatible_prc_release_within_cold_chain_passes(): void
    {
        $now = new DateTimeImmutable('2026-11-10 10:00:00');
        $expiry = new DateTimeImmutable('2026-11-25 10:00:00');

        $result = $this->service->verifyBloodRelease(
            'PMI-BDG-202611-0099',
            'O_POSITIVE',
            'O_POSITIVE',
            BloodBankHemovigilanceService::BLOOD_PRODUCT_PACKED_RED_CELLS,
            true, // Major crossmatch negative
            true, // Minor crossmatch negative
            4.2,  // 4.2°C (optimal 2-6°C)
            $expiry,
            $now
        );

        $this->assertTrue($result['is_safe_for_transfusion']);
        $this->assertSame(BloodBankHemovigilanceService::CROSSMATCH_COMPATIBLE, $result['crossmatch_verdict']);
        $this->assertSame('TEMPERATURE_OPTIMAL', $result['cold_chain_status']);
        $this->assertEmpty($result['contraindications']);
    }

    public function test_incompatible_crossmatch_blocks_release(): void
    {
        $now = new DateTimeImmutable('2026-11-10 10:00:00');
        $expiry = new DateTimeImmutable('2026-11-25 10:00:00');

        $result = $this->service->verifyBloodRelease(
            'PMI-BDG-202611-0100',
            'B_POSITIVE',
            'A_POSITIVE',
            BloodBankHemovigilanceService::BLOOD_PRODUCT_PACKED_RED_CELLS,
            false, // Major crossmatch POSITIF aglutinasi!
            true,
            4.0,
            $expiry,
            $now
        );

        $this->assertFalse($result['is_safe_for_transfusion']);
        $this->assertSame(BloodBankHemovigilanceService::CROSSMATCH_INCOMPATIBLE, $result['crossmatch_verdict']);
        $this->assertNotEmpty($result['contraindications']);
    }

    public function test_temperature_breach_blocks_plasma_release(): void
    {
        $now = new DateTimeImmutable('2026-11-10 10:00:00');
        $expiry = new DateTimeImmutable('2026-11-25 10:00:00');

        $result = $this->service->verifyBloodRelease(
            'PMI-BDG-202611-0101',
            'AB_POSITIVE',
            'AB_POSITIVE',
            BloodBankHemovigilanceService::BLOOD_PRODUCT_FRESH_FROZEN_PLASMA,
            true,
            true,
            -10.0, // Suhu -10°C (FFP butuh <= -18°C)
            $expiry,
            $now
        );

        $this->assertFalse($result['is_safe_for_transfusion']);
        $this->assertSame('TEMPERATURE_BREACH', $result['cold_chain_status']);
    }
}
