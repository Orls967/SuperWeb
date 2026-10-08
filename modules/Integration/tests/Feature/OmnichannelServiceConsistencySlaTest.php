<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\OmnichannelServiceConsistencySlaService;
use Tests\TestCase;

class OmnichannelServiceConsistencySlaTest extends TestCase
{
    use RefreshDatabase;

    protected OmnichannelServiceConsistencySlaService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(OmnichannelServiceConsistencySlaService::class);
    }

    public function test_sla_measurement_breach_and_credit_issuance(): void
    {
        // 1. Enterprise SLA target 30 minutes (281.1 & 281.4)
        $this->service->registerSla('SLA-ENTERPRISE-SUPPORT', 'ENTERPRISE', 30, 'CONTRACT');

        // 2. Met SLA -> no breach, $0 credit
        $metSla = $this->service->recordSlaMeasurement('SLA-ENTERPRISE-SUPPORT', 20);
        $this->assertFalse((bool) $metSla->is_sla_breached);
        $this->assertEquals(0.0, (float) $metSla->sla_credit_issued_usd);

        // 3. Breached SLA (75 mins > 30 mins) -> automated $100 credit issued (281.1 & 281.4)
        $breachedSla = $this->service->recordSlaMeasurement('SLA-ENTERPRISE-SUPPORT', 75);
        $this->assertTrue((bool) $breachedSla->is_sla_breached);
        $this->assertEquals(100.0, (float) $breachedSla->sla_credit_issued_usd);
    }

    public function test_channel_price_parity_monitoring(): void
    {
        // 1. Consistent pricing across all 4 channels (281.2)
        $parityOk = $this->service->evaluateChannelParity('SKU-EV-TIRE-01', 150.0, 150.0, 150.0, 150.0);
        $this->assertFalse((bool) $parityOk->is_parity_divergent);

        // 2. Divergent pricing detected (Web $150 vs App $135) (281.2 & 281.4)
        $parityBad = $this->service->evaluateChannelParity('SKU-EV-TIRE-02', 150.0, 135.0, 150.0, 150.0);
        $this->assertTrue((bool) $parityBad->is_parity_divergent);
    }

    public function test_escalation_warm_handoff_with_context_and_repeat_alert(): void
    {
        // 1. Open support case (281.3)
        $case = $this->service->openSupportCase('CASE-2026-CRM-01', 'CUST-TELKOMSEL', 'NETWORK_LATENCY');
        $this->assertEquals('TIER_1', $case->current_tier);

        // 2. Escalation without required context template is rejected (281.6 Edge Case)
        try {
            $this->service->escalateCaseWithContext('CASE-2026-CRM-01', 'TIER_2', []);
            $this->fail('Expected exception for empty escalation context');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('requires mandatory context template', $e->getMessage());
        }

        // 3. Escalation with full context succeeds (281.3 & 281.4)
        $escalated = $this->service->escalateCaseWithContext('CASE-2026-CRM-01', 'TIER_2', [
            'root_cause_summary' => 'Fiber optic junction flap at Sorong node',
            'diagnostic_ping_ms' => 450,
        ]);
        $this->assertEquals('TIER_2', $escalated->current_tier);
        $this->assertNotNull($escalated->warm_handoff_context_json);

        // 4. Repeat contact increments count; 3rd contact triggers supervisor alert (281.7)
        $c2 = $this->service->recordRepeatContact('CASE-2026-CRM-01'); // 2nd contact
        $this->assertFalse((bool) $c2->supervisor_alert_triggered);

        $c3 = $this->service->recordRepeatContact('CASE-2026-CRM-01'); // 3rd contact
        $this->assertTrue((bool) $c3->supervisor_alert_triggered);
        $this->assertEquals(3, (int) $c3->repeat_contact_count);
    }

    public function test_omnichannel_service_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->registerSla('SLA-AUD', 'CONSUMER', 60);
        $this->service->recordSlaMeasurement('SLA-AUD', 30);
        $this->service->evaluateChannelParity('ITEM-AUD', 10.0, 10.0, 10.0, 10.0);
        $this->service->openSupportCase('CASE-AUD', 'C1', 'CAT');

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: breached SLA without compensation credit
        DB::table('customer_service_slas')->insert([
            'sla_code' => 'SLA-UNCREDITED-BREACH',
            'customer_segment' => 'ENTERPRISE',
            'source_type' => 'CONTRACT',
            'target_response_minutes' => 15,
            'actual_response_minutes' => 120,
            'is_sla_breached' => true,
            'sla_credit_issued_usd' => 0.0, // Discrepancy!
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
