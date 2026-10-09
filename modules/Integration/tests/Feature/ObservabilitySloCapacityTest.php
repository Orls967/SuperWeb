<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Integration\Application\Services\ObservabilitySloCapacityService;
use Tests\TestCase;

class ObservabilitySloCapacityTest extends TestCase
{
    use RefreshDatabase;

    protected ObservabilitySloCapacityService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ObservabilitySloCapacityService::class);
    }

    public function test_golden_signals_alerting_and_capacity_forecasting(): void
    {
        // 431.1 Record golden signals with business overlay
        $signal = $this->service->recordGoldenSignals(
            serviceName: 'PaymentCoreService',
            trafficRps: 1250.00,
            errorRatePercent: 0.05,
            latencyP99Ms: 45.20,
            saturationPercent: 42.00,
            businessMetric: 'successful_transactions',
            businessVolume: 1248.00
        );

        $this->assertEquals('PaymentCoreService', $signal->service_name);

        // 431.2 & 431.4 Fire alert with valid runbook
        $alert = $this->service->fireAlert(
            alertCode: 'ALT-LATENCY-P99-01',
            serviceName: 'PaymentCoreService',
            severity: 'page',
            dedupFingerprint: 'payment_core_latency_spike',
            runbookUrl: 'https://runbooks.autoserve.internal/payment/latency-spike'
        );

        $this->assertEquals('firing', $alert->status);
        $this->assertFalse((bool) $alert->is_suppressed);
        $this->assertNotNull($alert->ticket_id);

        // 431.3 Deterministic capacity forecasting
        $forecast = $this->service->forecastCapacity(currentRps: 2000, growthRatePercent: 50);
        $this->assertEquals(3000.00, $forecast['projected_rps']);
        $this->assertEquals(6, $forecast['recommended_instances']); // 3000 / 500

        // 431.4 Audit clean
        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);
    }

    public function test_missing_runbook_blocked_and_alert_storm_suppression_edge_cases(): void
    {
        // 431.2 & 431.4 Invalid/missing runbook blocked
        try {
            $this->service->fireAlert(
                alertCode: 'ALT-NO-RUNBOOK',
                serviceName: 'OrderService',
                severity: 'page',
                dedupFingerprint: 'order_fail',
                runbookUrl: '' // Invalid!
            );
            $this->fail('Expected exception for missing runbook URL');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Alert must contain a valid, accessible runbook URL', $e->getMessage());
        }

        // 431.5 Edge case: Repeated alerts with same fingerprint are suppressed into same ticket
        $firstAlert = $this->service->fireAlert('ALT-STORM-1', 'OrderService', 'page', 'storm_db_conn', 'https://runbooks.autoserve.internal/db');
        $secondAlert = $this->service->fireAlert('ALT-STORM-2', 'OrderService', 'page', 'storm_db_conn', 'https://runbooks.autoserve.internal/db');

        $this->assertFalse((bool) $firstAlert->is_suppressed);
        $this->assertTrue((bool) $secondAlert->is_suppressed);
        $this->assertEquals($firstAlert->ticket_id, $secondAlert->ticket_id);
    }
}
