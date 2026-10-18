<?php

declare(strict_types=1);

namespace Modules\Core\tests\Feature\Resilience;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Application\Services\Resilience\MultiRegionResilienceEngineService;
use Tests\TestCase;

class MultiRegionResilienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_evaluates_multi_region_replication_topology_and_sla_thresholds(): void
    {
        /** @var MultiRegionResilienceEngineService $service */
        $service = app(MultiRegionResilienceEngineService::class);

        $topology = $service->getReplicationTopology();

        $this->assertSame('ACTIVE_PASSIVE_GLOBAL_READ_REPLICA', $topology['topology_mode']);
        $this->assertSame('ap-southeast-3', $topology['primary']['region']);
        $this->assertSame('ap-southeast-1', $topology['dr_standby']['region']);
        $this->assertSame(60, $topology['sla_targets']['rpo_target_seconds']);
        $this->assertSame(900, $topology['sla_targets']['rto_target_seconds']);

        // Test compliant SLA evaluation
        $compliantEval = $service->evaluateSlaCompliance(rpoSeconds: 15, rtoSeconds: 150);
        $this->assertTrue($compliantEval['compliant']);
        $this->assertStringContainsString('SLA Compliance OK', $compliantEval['message']);

        // Test non-compliant RPO violation (> 60s)
        $rpoViolation = $service->evaluateSlaCompliance(rpoSeconds: 75, rtoSeconds: 150);
        $this->assertFalse($rpoViolation['compliant']);
        $this->assertStringContainsString('SLA Violation', $rpoViolation['message']);

        // Test non-compliant RTO violation (> 900s)
        $rtoViolation = $service->evaluateSlaCompliance(rpoSeconds: 10, rtoSeconds: 950);
        $this->assertFalse($rtoViolation['compliant']);
        $this->assertStringContainsString('SLA Violation', $rtoViolation['message']);
    }
}
