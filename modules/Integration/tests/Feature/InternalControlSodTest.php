<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Integration\Application\Services\InternalControlSodService;
use Tests\TestCase;

/**
 * Fase 203 — Risiko: Internal Control, SoD 30 Lini & Control Testing Tests
 *
 * Covers:
 *  (a) Segregation of Duties (SoD) detects conflicting roles on single user
 *  (b) compatible role combinations pass without conflict
 *  (c) emergency break-glass procedure logs justification and flags compliance audit
 *  (d) enterprise:audit = 0 discrepancy
 */
class InternalControlSodTest extends TestCase
{
    use RefreshDatabase;

    protected InternalControlSodService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(InternalControlSodService::class);
    }

    /**
     * (a) & (b) SoD conflict detection.
     */
    public function test_sod_conflict_detection(): void
    {
        // 1. Conflicting roles: PO_CREATOR + PO_APPROVER on same user -> DETECTED
        $resConflict = $this->service->checkUserSod('USR-001', 'L04_SUPPLY_CHAIN', ['PO_CREATOR', 'PO_APPROVER']);
        $this->assertTrue((bool) $resConflict->has_sod_conflict);
        $this->assertSame('UNRESOLVED', $resConflict->remediation_status);
        $this->assertStringContainsString('SoD Conflict', $resConflict->conflicting_roles_detail);

        // 2. Compatible roles: PO_CREATOR + INVENTORY_VIEWER -> CLEAN
        $resClean = $this->service->checkUserSod('USR-002', 'L04_SUPPLY_CHAIN', ['PO_CREATOR', 'INVENTORY_VIEWER']);
        $this->assertFalse((bool) $resClean->has_sod_conflict);
        $this->assertSame('RESOLVED', $resClean->remediation_status);
    }

    /**
     * (c) Privileged break-glass logging.
     */
    public function test_break_glass_privileged_access(): void
    {
        $bg = $this->service->recordBreakGlassAccess('ADM-SUPER-99', 'RESTART_DATABASE_CLUSTER', 'Critical outage during month-end closing, approved by CTO.');
        $this->assertSame('RESTART_DATABASE_CLUSTER', $bg->emergency_action);
        $this->assertTrue((bool) $bg->audited_by_compliance);
    }

    /**
     * (d) Audit status healthy with 0 discrepancies (when conflicts resolved).
     */
    public function test_internal_control_sod_audit(): void
    {
        // Truncate conflicts and keep clean check
        DB::table('erm_sod_conflict_checks')->truncate();
        $this->service->checkUserSod('USR-002', 'L04_SUPPLY_CHAIN', ['PO_CREATOR', 'INVENTORY_VIEWER']);

        $audit = $this->service->audit();
        $this->assertSame('HEALTHY', $audit['status']);
        $this->assertSame(0, $audit['discrepancy_count']);
    }
}
