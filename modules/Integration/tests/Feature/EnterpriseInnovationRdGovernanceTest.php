<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Integration\Application\Services\EnterpriseInnovationRdGovernanceService;
use Tests\TestCase;

class EnterpriseInnovationRdGovernanceTest extends TestCase
{
    use RefreshDatabase;

    protected EnterpriseInnovationRdGovernanceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EnterpriseInnovationRdGovernanceService::class);
    }

    public function test_innovation_funnel_and_ip_lifecycle_flow(): void
    {
        // 460.1 & 460.2 Register exploratory Horizon 3 innovation project
        $proj = $this->service->registerInnovationProject(
            code: 'RD-QUANTUM-BATTERY',
            title: 'Solid-State Electrolyte Fast Charging Prototype',
            stage: 'experiment',
            horizon: 'horizon_3',
            budget: 5000000000.00
        );

        $this->assertEquals('RD-QUANTUM-BATTERY', $proj->project_code);
        $this->assertEquals('active', $proj->status);

        // 460.3 Register patent IP asset
        $ip = $this->service->registerIpAsset(
            ipCode: 'PAT-BATTERY-ANODE-2026',
            type: 'patent',
            title: 'Silicon-Carbon Nanocomposite Anode Architecture',
            renewalDeadline: now()->addYears(2)->toDateString()
        );

        $this->assertEquals('PAT-BATTERY-ANODE-2026', $ip->ip_code);
        $this->assertEquals('active', $ip->status);

        // 460.4 Audit clean
        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);
    }

    public function test_kill_criteria_and_ip_abandonment_edge_cases(): void
    {
        // 460.1 Trigger kill criteria for failed experiment
        $this->service->registerInnovationProject('RD-FAIL-PROJ', 'Failed Project', 'pilot', 'horizon_1', 1000000000.00);
        $killed = $this->service->triggerKillCriteria('RD-FAIL-PROJ');
        $this->assertEquals('killed_reallocated', $killed->status);
        $this->assertTrue((bool) $killed->kill_criteria_triggered);

        // 460.5 IP deadline alert check
        $this->service->registerIpAsset('PAT-EXPIRING-SOON', 'patent', 'Expiring Patent', now()->addDays(20)->toDateString());
        $alerted = $this->service->checkIpDeadlines();
        $this->assertGreaterThan(0, $alerted);

        // Abandonment without rationale is blocked
        try {
            $this->service->resolveIpDeadline('PAT-EXPIRING-SOON', false, null);
            $this->fail('Expected exception for undocumented abandonment');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('requires documented commercial/strategic reasoning', $e->getMessage());
        }

        // Abandonment with rationale succeeds
        $abandoned = $this->service->resolveIpDeadline('PAT-EXPIRING-SOON', false, 'Superseded by generation 2 cathode patent portfolio');
        $this->assertEquals('abandoned', $abandoned->status);
        $this->assertNotNull($abandoned->abandonment_reason);
    }
}
