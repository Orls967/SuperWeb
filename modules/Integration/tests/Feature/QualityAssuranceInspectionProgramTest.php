<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Integration\Application\Services\QualityAssuranceInspectionProgramService;
use Tests\TestCase;

class QualityAssuranceInspectionProgramTest extends TestCase
{
    use RefreshDatabase;

    protected QualityAssuranceInspectionProgramService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(QualityAssuranceInspectionProgramService::class);
    }

    public function test_inspection_execution_and_coq_reconciliation(): void
    {
        // 412.1 & 412.2 Inspection execution with calibrated tools
        $insp = $this->service->executeInspection(
            inspectionCode: 'QC-INSP-2026-001',
            lotOrUnitId: 'LOT-STEEL-BEAM-99',
            inspectorId: 'QC-ENG-05',
            inspectorQualified: true,
            toolCalibrationCert: 'CAL-CERT-ISO17025-2026',
            calibrationValid: true,
            result: 'pass'
        );

        $this->assertEquals('QC-INSP-2026-001', $insp->inspection_code);
        $this->assertEquals('pass', $insp->result);

        // 412.5 Independent reinspection
        $reinspected = $this->service->performIndependentReinspection('QC-INSP-2026-001', 'SENIOR-AUDITOR-01');
        $this->assertTrue((bool) $reinspected->is_reinspected_independently);

        // 412.3 Record cost of quality
        $coq = $this->service->recordCostOfQuality(
            recordCode: 'COQ-MFG-2026-Q3',
            businessLine: 'MANUFACTURING',
            preventionCost: 20000000.00,
            appraisalCost: 35000000.00,
            internalFailureCost: 15000000.00,
            externalFailureCost: 5000000.00
        );

        $this->assertEquals(75000000.00, (float) $coq->total_coq);

        // 412.4 Audit clean
        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);
    }

    public function test_uncalibrated_tool_blocks_inspection_edge_case(): void
    {
        // 412.4 Calibration gate
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Measurement tool lacks valid calibration certification');

        $this->service->executeInspection(
            inspectionCode: 'QC-INSP-UNCALIBRATED',
            lotOrUnitId: 'UNIT-ENGINE-101',
            inspectorId: 'QC-ENG-05',
            inspectorQualified: true,
            toolCalibrationCert: null, // Missing!
            calibrationValid: false,
            result: 'pass'
        );
    }
}
