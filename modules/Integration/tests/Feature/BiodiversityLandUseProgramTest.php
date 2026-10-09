<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Integration\Application\Services\BiodiversityLandUseProgramService;
use Tests\TestCase;

class BiodiversityLandUseProgramTest extends TestCase
{
    use RefreshDatabase;

    protected BiodiversityLandUseProgramService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(BiodiversityLandUseProgramService::class);
    }

    public function test_biodiversity_plot_monitoring_and_offset_issuance_flow(): void
    {
        // 445.1 Register protected land plot
        $plot = $this->service->registerPlot('PLOT-BORNEO-01', 'Taman Nasional Tanjung Puting Buffer Zone', 'avoid');
        $this->assertEquals('PLOT-BORNEO-01', $plot->plot_code);
        $this->assertFalse((bool) $plot->disturbance_detected);

        // 445.2 & 445.5 Detect disturbance -> auto generates remediation task
        $disturbed = $this->service->flagPlotDisturbance('PLOT-BORNEO-01');
        $this->assertTrue((bool) $disturbed->disturbance_detected);
        $this->assertNotNull($disturbed->remediation_task_id);

        // Complete remediation
        $remediated = $this->service->completePlotRemediation('PLOT-BORNEO-01');
        $this->assertTrue((bool) $remediated->remediation_completed);
        $this->assertFalse((bool) $remediated->disturbance_detected);

        // 445.3 & 445.6 Register offset project with avoidance non-feasibility proof
        $proj = $this->service->registerOffsetProject(
            code: 'OFF-PEATLAND-RESTORE',
            title: 'Katingan Peatland Biodiversity Restoration',
            avoidanceInfeasible: true,
            additionality: true,
            permanence: true,
            communityConsent: true
        );

        $this->assertFalse((bool) $proj->credit_issuance_authorized);

        // Authorize credit issuance
        $auth = $this->service->authorizeOffsetIssuance('OFF-PEATLAND-RESTORE');
        $this->assertTrue((bool) $auth->credit_issuance_authorized);

        // 445.4 Audit clean
        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);
    }

    public function test_offset_without_avoidance_proof_blocked_edge_case(): void
    {
        // 445.4 & 445.6 Attempting to authorize offset without proving avoidance infeasibility is blocked
        $this->service->registerOffsetProject(
            code: 'OFF-LAZY-SUBSTITUTE',
            title: 'Convenient Offset Alternative',
            avoidanceInfeasible: false, // Feasible to avoid on-site!
            additionality: true,
            permanence: true,
            communityConsent: true
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Biodiversity offset cannot substitute for on-site avoidance');

        $this->service->authorizeOffsetIssuance('OFF-LAZY-SUBSTITUTE');
    }
}
