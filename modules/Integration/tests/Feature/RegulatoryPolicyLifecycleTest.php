<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Integration\Application\Services\RegulatoryPolicyLifecycleService;
use Tests\TestCase;

/**
 * Fase 207 — Risiko: Regulatory Intelligence & Policy Lifecycle Tests
 *
 * Covers:
 *  (a) regulatory change feed creates actionable implementation tasks
 *  (b) policy draft requires legal counsel approval before publishing
 *  (c) employee policy acknowledgment tracking
 *  (d) compliance:audit = 0 discrepancy
 */
class RegulatoryPolicyLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected RegulatoryPolicyLifecycleService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(RegulatoryPolicyLifecycleService::class);
    }

    /**
     * (a) Regulatory change feed task assignment.
     */
    public function test_regulatory_change_task_ingestion(): void
    {
        $task = $this->service->ingestRegulatoryChange(
            'ID',
            'L05_SYARIAH_BANKING',
            'Penyesuaian batas maksimum pembiayaan nasabah per POJK 2026',
            'Tim Kepatuhan Syariah & Legal'
        );

        $this->assertSame('OPEN', $task->status);
        $this->assertSame('ID', $task->jurisdiction);
        $this->assertSame('L05_SYARIAH_BANKING', $task->affected_domain);
    }

    /**
     * (b) & (c) Policy lifecycle approval and employee acknowledgment.
     */
    public function test_policy_approval_and_acknowledgment(): void
    {
        $policy = $this->service->createPolicyDraft('POL-CYBER-01', 'Kebijakan Pengelolaan Akses Istimewa');
        $this->assertSame('DRAFT', $policy->status);

        // 1. Publish without legal approval -> Exception
        try {
            $this->service->publishPolicy('POL-CYBER-01', '   ');
            $this->fail('Expected exception for unapproved policy.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('Policy cannot be published without legal counsel approval', $e->getMessage());
        }

        // 2. Publish with approval -> PUBLISHED
        $published = $this->service->publishPolicy('POL-CYBER-01', 'Kepala Divisi Hukum');
        $this->assertSame('PUBLISHED', $published->status);
        $this->assertSame('Kepala Divisi Hukum', $published->legal_approval_by);

        // 3. Employee acknowledgment
        $ack = $this->service->recordAcknowledgment('POL-CYBER-01', 'EMP-1092');
        $this->assertTrue($ack);
    }

    /**
     * (d) Audit status healthy with 0 discrepancies.
     */
    public function test_regulatory_policy_audit(): void
    {
        $audit = $this->service->audit();
        $this->assertSame('HEALTHY', $audit['status']);
        $this->assertSame(0, $audit['discrepancy_count']);
    }
}
