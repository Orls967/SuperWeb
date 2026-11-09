<?php

declare(strict_types=1);

namespace Modules\Hospital\Tests\Unit;

use DateTimeImmutable;
use Modules\Hospital\Domain\Services\HospitalBedTurnaroundOptimizationService;
use PHPUnit\Framework\TestCase;

class HospitalBedTurnaroundOptimizationServiceTest extends TestCase
{
    private HospitalBedTurnaroundOptimizationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new HospitalBedTurnaroundOptimizationService();
    }

    public function test_routine_bed_turnaround_within_benchmark_passes(): void
    {
        $discharge = new DateTimeImmutable('2026-11-10 09:00:00');
        $cleaningStart = new DateTimeImmutable('2026-11-10 09:20:00');
        $ready = new DateTimeImmutable('2026-11-10 10:30:00'); // 90 menit (< 120 menit)

        $result = $this->service->evaluateBedTurnaround(
            'BED-ICU-05',
            'ROUTINE_DISCHARGE',
            $discharge,
            $cleaningStart,
            $ready,
            true // ATP passed
        );

        $this->assertSame(90, $result['turnaround_duration_minutes']);
        $this->assertTrue($result['is_within_benchmark_time']);
        $this->assertTrue($result['cleanliness_verified']);
        $this->assertSame(HospitalBedTurnaroundOptimizationService::BED_STATUS_INSPECTED_READY, $result['final_bed_status']);
        $this->assertSame('EXCELLENT', $result['efficiency_rating']);
        $this->assertNull($result['corrective_action']);
    }

    public function test_failed_atp_check_forces_bed_status_back_to_dirty(): void
    {
        $discharge = new DateTimeImmutable('2026-11-10 09:00:00');
        $cleaningStart = new DateTimeImmutable('2026-11-10 09:15:00');
        $ready = new DateTimeImmutable('2026-11-10 10:00:00');

        $result = $this->service->evaluateBedTurnaround(
            'BED-WARD-2A-10',
            'ROUTINE_DISCHARGE',
            $discharge,
            $cleaningStart,
            $ready,
            false // ATP GAGAL
        );

        $this->assertFalse($result['cleanliness_verified']);
        $this->assertSame(HospitalBedTurnaroundOptimizationService::BED_STATUS_DISCHARGED_DIRTY, $result['final_bed_status']);
        $this->assertStringContainsString('kontaminasi biologis', $result['corrective_action']);
    }
}
