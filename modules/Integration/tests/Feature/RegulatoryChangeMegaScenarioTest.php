<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Integration\Application\Services\RegulatoryChangeMegaScenarioService;
use Tests\TestCase;

class RegulatoryChangeMegaScenarioTest extends TestCase
{
    use RefreshDatabase;

    protected RegulatoryChangeMegaScenarioService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(RegulatoryChangeMegaScenarioService::class);
    }

    public function test_regulatory_change_multi_line_compliance_flow(): void
    {
        // 468.1 Log multi-line regulatory change (EU CBAM + Indonesian Carbon Tax)
        $reg = $this->service->logRegulatoryChange(
            code: 'REG-CBAM-CARBON-2026',
            title: 'Carbon Border Adjustment Mechanism & Local Carbon Tax Reporting',
            affectedLines: ['LINE-FREIGHT-FORWARDING', 'LINE-FLEET-LOGISTICS', 'LINE-SUPPLY-CHAIN'],
            isImmediateEmergency: false
        );

        $this->assertEquals('REG-CBAM-CARBON-2026', $reg->regulation_code);
        $this->assertEquals('analyzing', $reg->status);

        // 468.2 & 468.4 Certify compliance after building and testing controls
        $cert = $this->service->certifyCompliance('REG-CBAM-CARBON-2026', true, true);
        $this->assertEquals('compliant', $cert->status);
        $this->assertTrue((bool) $cert->controls_built_and_tested);
        $this->assertTrue((bool) $cert->compliance_gap_disclosed_to_auditor);

        // 468.4 Audit clean
        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);
    }

    public function test_emergency_regulatory_enactment_and_hidden_gap_blocked_edge_cases(): void
    {
        // 468.5 Edge case: Immediate emergency regulatory enactment triggers emergency priority build
        $emer = $this->service->logRegulatoryChange(
            code: 'REG-DATA-SOVEREIGNTY',
            title: 'Immediate Cross-Border Data Residency Injunction',
            affectedLines: ['LINE-ALL'],
            isImmediateEmergency: true
        );
        $this->assertEquals('emergency_priority_build', $emer->status);
        $this->assertTrue((bool) $emer->is_immediate_emergency_enactment);

        // 468.6 Risk: Certifying compliance while concealing unaddressed gaps is strictly blocked
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unaddressed compliance gaps must be disclosed to independent auditor');

        $this->service->certifyCompliance('REG-DATA-SOVEREIGNTY', true, false); // Hidden gap!
    }
}
