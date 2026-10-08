<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Integration\Application\Services\FieldServiceSlaService;
use Tests\TestCase;

/**
 * Fase 216 — Operasi: Field Service, Workforce Mobility & SLA Engine Tests
 *
 * Covers:
 *  (a) technician dispatch verifies required certifications
 *  (b) uncertified technician rejected from critical task dispatch
 *  (c) SLA engine calculates breach and posts credit penalty automatically
 *  (d) field:audit = 0 discrepancy
 */
class FieldServiceSlaTest extends TestCase
{
    use RefreshDatabase;

    protected FieldServiceSlaService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(FieldServiceSlaService::class);
    }

    /**
     * (a) & (b) Technician dispatch certification gating.
     */
    public function test_technician_dispatch_certification_gating(): void
    {
        $this->service->registerTechnician('TECH-101', 'Budi Santoso', 'MEDICAL_EQUIPMENT', [
            'CERT-MRI-LEVEL2',
            'CERT-RADIATION-SAFETY',
        ]);

        // 1. Qualified dispatch -> SUCCESS
        $d1 = $this->service->dispatchTechnician('TECH-101', 'CERT-MRI-LEVEL2', 'ORD-MED-9901');
        $this->assertSame('DISPATCHED', $d1->status);
        $this->assertSame('CERT-MRI-LEVEL2', $d1->required_certification);

        // 2. Unqualified dispatch -> Exception
        try {
            $this->service->dispatchTechnician('TECH-101', 'CERT-ROBOTIC-SURGERY', 'ORD-MED-9902');
            $this->fail('Expected exception for uncertified technician dispatch.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('does not hold required certification', $e->getMessage());
        }
    }

    /**
     * (c) SLA breach detection and automated penalty calculation.
     */
    public function test_sla_breach_and_penalty(): void
    {
        // 1. Within SLA: Target 60m, Actual 45m -> Clean, 0 penalty
        $sla1 = $this->service->evaluateSlaPerformance('ORD-001', 60, 45);
        $this->assertFalse((bool) $sla1->is_breached);
        $this->assertEquals(0.00, (float) $sla1->penalty_credit_amount_idr);
        $this->assertSame('NONE', $sla1->penalty_status);

        // 2. Breached SLA: Target 60m, Actual 90m (30m overdue @ 10,000 IDR/m = 300,000 IDR) -> PENALTY_CREDITED
        $sla2 = $this->service->evaluateSlaPerformance('ORD-002', 60, 90, 10000.0);
        $this->assertTrue((bool) $sla2->is_breached);
        $this->assertEquals(300000.00, (float) $sla2->penalty_credit_amount_idr);
        $this->assertSame('PENALTY_CREDITED', $sla2->penalty_status);
    }

    /**
     * (d) Audit status healthy with 0 discrepancies.
     */
    public function test_field_service_sla_audit(): void
    {
        $audit = $this->service->audit();
        $this->assertSame('HEALTHY', $audit['status']);
        $this->assertSame(0, $audit['discrepancy_count']);
    }
}
