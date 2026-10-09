<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Integration\Application\Services\CyberResilienceService;
use Tests\TestCase;

/**
 * Fase 204 — Risiko: Cyber, Data Breach & Operational Resilience Tests
 *
 * Covers:
 *  (a) vulnerability management tracks SLA and closes cleanly within SLA window
 *  (b) incident containment revokes tokens and isolates affected module immediately
 *  (c) ransomware drill verifies ledger reconstruction integrity with Σ=0 discrepancy
 *  (d) dr:audit = 0 discrepancy
 */
class CyberResilienceTest extends TestCase
{
    use RefreshDatabase;

    protected CyberResilienceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CyberResilienceService::class);
    }

    /**
     * (a) Vulnerability SLA logging and resolution.
     */
    public function test_vulnerability_sla_logging_and_resolution(): void
    {
        // Critical severity has 24-hour SLA
        $vuln = $this->service->logVulnerability('PAYMENT_GATEWAY_ADAPTER', 'CRITICAL');
        $this->assertSame('CRITICAL', $vuln->severity);
        $this->assertSame(24, (int) $vuln->sla_hours_remediation);
        $this->assertSame('OPEN', $vuln->remediation_status);

        // Immediate resolution -> CLOSED (within SLA)
        $resolved = $this->service->resolveVulnerability($vuln->vuln_code);
        $this->assertSame('CLOSED', $resolved->remediation_status);
        $this->assertNotNull($resolved->resolved_at);
    }

    /**
     * (b) & (c) Incident containment and ransomware recovery ledger drill.
     */
    public function test_incident_containment_and_ledger_reconstruction(): void
    {
        $incident = $this->service->executeIncidentContainment('HOTEL_RESERVATION_CORE');

        // Tokens revoked & module isolated
        $this->assertTrue((bool) $incident->access_tokens_revoked);
        $this->assertTrue((bool) $incident->module_isolated);

        // Ransomware recovery drill: ledger parity verified with Σ=0 discrepancy
        $this->assertEquals(0.00, (float) $incident->reconstructed_ledger_discrepancy);
    }

    /**
     * (d) Audit status healthy with 0 discrepancies.
     */
    public function test_cyber_resilience_audit(): void
    {
        $audit = $this->service->audit();
        $this->assertSame('HEALTHY', $audit['status']);
        $this->assertSame(0, $audit['discrepancy_count']);
    }
}
