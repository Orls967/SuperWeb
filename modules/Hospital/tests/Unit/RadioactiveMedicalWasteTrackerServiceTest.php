<?php

declare(strict_types=1);

namespace Modules\Hospital\Tests\Unit;

use DateTimeImmutable;
use Modules\Hospital\Domain\Services\RadioactiveMedicalWasteTrackerService;
use PHPUnit\Framework\TestCase;

class RadioactiveMedicalWasteTrackerServiceTest extends TestCase
{
    private RadioactiveMedicalWasteTrackerService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new RadioactiveMedicalWasteTrackerService();
    }

    public function test_tc99m_decay_calculation_and_clearance_reached(): void
    {
        $start = new DateTimeImmutable('2026-11-01 08:00:00');
        // Tc-99m half life = 6 jam. Setelah 60 jam (10 half lives), aktivitas turun 2^10 = 1024 kali
        $current = new DateTimeImmutable('2026-11-03 20:00:00'); // 60 jam

        $result = $this->service->calculateDecayStatus(
            'TC_99M',
            10.0, // 10 MBq = 10.000.000 Bq
            5000.0, // 5000 gram limbah
            $start,
            $current
        );

        $this->assertSame(60.0, $result['elapsed_hours']);
        $this->assertLessThan(0.01, $result['current_activity_mega_bq']);
        // 10.000.000 / 1024 = 9765 Bq / 5000g = 1.95 Bq/g (< 10 Bq/g clearance)
        $this->assertTrue($result['is_cleared_for_medical_disposal']);
    }

    public function test_fresh_isotope_requires_shielding_and_not_cleared(): void
    {
        $start = new DateTimeImmutable('2026-11-09 08:00:00');
        $current = new DateTimeImmutable('2026-11-09 10:00:00'); // baru 2 jam

        $result = $this->service->calculateDecayStatus(
            'I_131',
            500.0, // 500 MBq
            2000.0,
            $start,
            $current
        );

        $this->assertFalse($result['is_cleared_for_medical_disposal']);
        $this->assertTrue($result['decay_room_lead_shielding_required']);
        $this->assertGreaterThan(100.0, $result['estimated_hours_until_clearance']);
    }
}
