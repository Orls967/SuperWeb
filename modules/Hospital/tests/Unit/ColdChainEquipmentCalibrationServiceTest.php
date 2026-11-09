<?php

declare(strict_types=1);

namespace Modules\Hospital\Tests\Unit;

use DateTimeImmutable;
use Modules\Hospital\Domain\Services\ColdChainEquipmentCalibrationService;
use PHPUnit\Framework\TestCase;

class ColdChainEquipmentCalibrationServiceTest extends TestCase
{
    private ColdChainEquipmentCalibrationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ColdChainEquipmentCalibrationService;
    }

    public function test_sensor_is_calibrated_and_accurate_within_tolerance(): void
    {
        $calDate = new DateTimeImmutable('2026-01-15');
        $evalDate = new DateTimeImmutable('2026-06-01');

        $points = [
            ['set_point' => 2.0, 'measured_average' => 2.05, 'expanded_uncertainty' => 0.08],
            ['set_point' => 5.0, 'measured_average' => 5.02, 'expanded_uncertainty' => 0.08],
            ['set_point' => 8.0, 'measured_average' => 7.96, 'expanded_uncertainty' => 0.08],
        ];

        $result = $this->service->evaluateCalibrationStatus('PT100-FRIDGE-01', $calDate, $evalDate, $points);

        $this->assertSame(ColdChainEquipmentCalibrationService::STATUS_CALIBRATED_ACCURATE, $result['status']);
        $this->assertTrue($result['is_valid_for_operation']);
        $this->assertFalse($result['requires_recalibration']);
        $this->assertLessThanOrEqual(0.5, $result['max_systematic_error']);
    }

    public function test_sensor_out_of_specification_exceeding_max_allowed_error(): void
    {
        $calDate = new DateTimeImmutable('2026-01-15');
        $evalDate = new DateTimeImmutable('2026-06-01');

        $points = [
            ['set_point' => 4.0, 'measured_average' => 4.65, 'expanded_uncertainty' => 0.12], // error 0.65 > 0.50
        ];

        $result = $this->service->evaluateCalibrationStatus('NTC-PROBE-09', $calDate, $evalDate, $points);

        $this->assertSame(ColdChainEquipmentCalibrationService::STATUS_OUT_OF_SPECIFICATION, $result['status']);
        $this->assertFalse($result['is_valid_for_operation']);
        $this->assertTrue($result['requires_recalibration']);
    }

    public function test_sensor_expired_calibration_due(): void
    {
        $calDate = new DateTimeImmutable('2025-01-01');
        $evalDate = new DateTimeImmutable('2026-03-01'); // > 365 hari

        $points = [
            ['set_point' => 4.0, 'measured_average' => 4.01, 'expanded_uncertainty' => 0.05],
        ];

        $result = $this->service->evaluateCalibrationStatus('LOGGER-EXPIRED', $calDate, $evalDate, $points);

        $this->assertSame(ColdChainEquipmentCalibrationService::STATUS_CALIBRATION_DUE, $result['status']);
        $this->assertFalse($result['is_valid_for_operation']);
        $this->assertTrue($result['requires_recalibration']);
    }
}
