<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Integration\Application\Services\ProjectPortfolioManagementService;
use Tests\TestCase;

/**
 * Fase 217 — Operasi: Project & Portfolio Management Tests
 *
 * Covers:
 *  (a) task acyclic dependency tracking
 *  (b) circular task dependencies thrown as exceptions
 *  (c) change request execution requires executive approval
 *  (d) ppm:audit = 0 discrepancy
 */
class ProjectPortfolioManagementTest extends TestCase
{
    use RefreshDatabase;

    protected ProjectPortfolioManagementService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ProjectPortfolioManagementService::class);
    }

    /**
     * (a) & (b) Task dependencies and circular cycle rejection.
     */
    public function test_task_dependency_cycle_rejection(): void
    {
        // 1. Valid linear dependency chain: Task B depends on Task A -> SUCCESS
        $t1 = $this->service->addTask('PRJ-EPC-01', 'TSK-FOUNDATION', 'Pondasi Struktur', null, 14);
        $t2 = $this->service->addTask('PRJ-EPC-01', 'TSK-PIPING', 'Instalasi Perpipaan', 'TSK-FOUNDATION', 7);
        $this->assertSame('TSK-FOUNDATION', $t2->predecessor_task_code);

        // 2. Self circular dependency -> Exception
        try {
            $this->service->addTask('PRJ-EPC-01', 'TSK-ELECTRICAL', 'Kabel Daya', 'TSK-ELECTRICAL', 5);
            $this->fail('Expected exception for self-referential task dependency.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('cannot depend on itself', $e->getMessage());
        }

        // 3. Mutually circular dependency (Task A depends back on Task B) -> Exception
        try {
            $this->service->addTask('PRJ-EPC-01', 'TSK-FOUNDATION', 'Pondasi Struktur', 'TSK-PIPING', 14);
            $this->fail('Expected exception for circular dependency cycle.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('Circular dependency cycle detected', $e->getMessage());
        }
    }

    /**
     * (c) Project change request approval lifecycle.
     */
    public function test_change_request_approval_lifecycle(): void
    {
        $cr = $this->service->submitChangeRequest(
            'PRJ-EPC-01',
            'Penambahan kapasitas turbin uap +5MW',
            2500000000.0,
            21
        );
        $this->assertSame('SUBMITTED', $cr->approval_status);

        // 1. Execute without approver -> Exception
        try {
            $this->service->executeChangeRequest($cr->change_request_code, '   ');
            $this->fail('Expected exception for execution without executive approver.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('Executive approver is mandatory', $e->getMessage());
        }

        // 2. Execute with approver -> EXECUTED
        $executed = $this->service->executeChangeRequest($cr->change_request_code, 'VP of Engineering');
        $this->assertSame('EXECUTED', $executed->approval_status);
        $this->assertSame('VP of Engineering', $executed->approved_by);
    }

    /**
     * (d) Audit status healthy with 0 discrepancies.
     */
    public function test_ppm_audit(): void
    {
        $audit = $this->service->audit();
        $this->assertSame('HEALTHY', $audit['status']);
        $this->assertSame(0, $audit['discrepancy_count']);
    }
}
