<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\ObservabilitySloEconomyService;
use Tests\TestCase;

class ObservabilitySloEconomyTest extends TestCase
{
    use RefreshDatabase;

    protected ObservabilitySloEconomyService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ObservabilitySloEconomyService::class);
    }

    public function test_slo_error_budget_burn_rate_alert_and_postmortem_obligation(): void
    {
        // 1. Register Payment Availability SLO (99.90%, 43.2 mins error budget) (256.1)
        $slo = $this->service->registerSloTarget(
            serviceName: 'PAYMENT',
            sliMetricName: 'AVAILABILITY',
            targetSloPct: 99.90,
            errorBudgetTotalMins: 43.20
        );

        // 2. High burn rate consumes budget and triggers alert (256.1 & 256.8)
        $consumed = $this->service->consumeErrorBudget(
            sloId: (int) $slo->id,
            minsConsumed: 20.0,
            burnRate: 3.5 // > 2.0 triggers alert
        );
        $this->assertTrue((bool) $consumed->burn_rate_alert_triggered);
        $this->assertFalse((bool) $consumed->postmortem_required);

        // 3. Complete exhaustion of error budget requires postmortem
        $exhausted = $this->service->consumeErrorBudget(
            sloId: (int) $slo->id,
            minsConsumed: 25.0, // 20 + 25 = 45 > 43.2
            burnRate: 1.0
        );
        $this->assertTrue((bool) $exhausted->postmortem_required);
        $this->assertFalse((bool) $exhausted->postmortem_completed);

        // 4. Complete postmortem
        $postmortemDone = $this->service->completePostmortem((int) $slo->id, 'Root cause: DB lock contention remediated with read replica.');
        $this->assertTrue((bool) $postmortemDone->postmortem_completed);
    }

    public function test_business_invariant_monitoring_and_meta_alert_on_monitor_failure(): void
    {
        // 1. Invariant satisfied: clean pass (256.3)
        $satisfied = $this->service->checkBusinessInvariant(
            invariantCode: 'INV-LEDGER-01',
            invariantType: 'LEDGER_SUM_ZERO',
            conditionSatisfied: true
        );
        $this->assertFalse((bool) $satisfied->is_violated);
        $this->assertNull($satisfied->incident_ticket_code);

        // 2. Invariant violated (e.g. stock goes negative) -> incident created (256.3)
        $violated = $this->service->checkBusinessInvariant(
            invariantCode: 'INV-STOCK-02',
            invariantType: 'NO_NEGATIVE_INVENTORY',
            conditionSatisfied: false
        );
        $this->assertTrue((bool) $violated->is_violated);
        $this->assertNotNull($violated->incident_ticket_code);
        $this->assertStringStartsWith('INC-ANOMALY-', $violated->incident_ticket_code);

        // 3. Monitor self-failure triggers meta-alert immediately (256.6 Edge Case: never silent fail)
        $monitorCrashed = $this->service->checkBusinessInvariant(
            invariantCode: 'INV-ESCROW-03',
            invariantType: 'ESCROW_EXACT_MATCH',
            conditionSatisfied: true,
            simulateMonitorSelfFailure: true
        );
        $this->assertFalse((bool) $monitorCrashed->monitor_healthy);
        $this->assertTrue((bool) $monitorCrashed->meta_alert_triggered);
    }

    public function test_distributed_trace_correlation_across_three_modules(): void
    {
        // 1. Success with 3 modules (256.2 & 256.5)
        $trace = $this->service->correlateDistributedTrace(
            traceId: 'TRC-PAY-ORD-INV-99',
            modules: ['PAYMENT_GATEWAY', 'ORDER_MANAGEMENT', 'INVENTORY_WMS'],
            statusCode: 200,
            durationMs: 145,
            auditTrailRef: 'AUD-TRAIL-2026-X'
        );
        $this->assertNotNull($trace);
        $this->assertEquals(200, (int) $trace->status_code);

        // 2. Failure when fewer than 3 modules provided
        $this->expectException(InvalidArgumentException::class);
        $this->service->correlateDistributedTrace(
            traceId: 'TRC-SHORT',
            modules: ['MODULE_A', 'MODULE_B'],
            statusCode: 200,
            durationMs: 50,
            auditTrailRef: 'REF'
        );
    }

    public function test_partner_sla_evidence_reporting(): void
    {
        // 99.85% uptime against 99.50% SLA threshold (256.4 & 256.7)
        $report = $this->service->generatePartnerSlaReport(
            partnerId: 'PARTNER-BANK-MANDIRI',
            measuredUptimePct: 99.85,
            slaThresholdPct: 99.50
        );

        $this->assertTrue((bool) $report->is_sla_met);
        $this->assertEquals(99.85, (float) $report->measured_uptime_pct);
    }

    public function test_observability_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $slo = $this->service->registerSloTarget('CLAIM', 'LATENCY');
        $this->service->checkBusinessInvariant('INV-AUD', 'NO_OVERSELL', true);
        $this->service->correlateDistributedTrace('TRC-AUD', ['A', 'B', 'C'], 200, 10, 'REF');
        $this->service->generatePartnerSlaReport('P-1', 99.9);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: monitor failure without meta-alert (silent fail!)
        DB::table('observability_business_invariants')->insert([
            'invariant_code' => 'INV-SILENT-FAIL',
            'invariant_type' => 'ESCROW',
            'is_violated' => false,
            'monitor_healthy' => false,
            'meta_alert_triggered' => false, // Discrepancy!
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
