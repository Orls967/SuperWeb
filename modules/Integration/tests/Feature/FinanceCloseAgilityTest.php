<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Integration\Application\Services\FinanceCloseAgilityService;
use Tests\TestCase;

/**
 * Fase 209 — Keuangan: Group Finance Operations & Close Agility Tests
 *
 * Covers:
 *  (a) subledger reconciliation locks period when variance is 0.00
 *  (b) period lock rejected when variance > 0
 *  (c) intercompany automated matching validates bilateral amounts
 *  (d) enterprise:audit = 0 discrepancy
 */
class FinanceCloseAgilityTest extends TestCase
{
    use RefreshDatabase;

    protected FinanceCloseAgilityService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(FinanceCloseAgilityService::class);
    }

    /**
     * (a) & (b) Continuous close subledger reconciliation & lock period.
     */
    public function test_subledger_reconciliation_and_period_lock(): void
    {
        // 1. Zero variance lock: GL = 50,000,000,000 IDR == Subledger -> SUCCESS
        $lock = $this->service->reconcileAndLockPeriod('2026-M10', 50000000000.0, 50000000000.0, 'Head of Group Accounting');
        $this->assertTrue((bool) $lock->is_period_locked);
        $this->assertEquals(0.00, (float) $lock->reconciliation_variance_idr);
        $this->assertSame('Head of Group Accounting', $lock->locked_by);

        // 2. Variance detected: GL != Subledger -> Exception
        try {
            $this->service->reconcileAndLockPeriod('2026-M11', 50000000000.0, 49800000000.0, 'Head of Group Accounting');
            $this->fail('Expected exception for un-reconciled period close.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Variance IDR', $e->getMessage());
        }
    }

    /**
     * (c) Intercompany matching and bilateral balance.
     */
    public function test_intercompany_matching(): void
    {
        // 1. Balanced IC trade: Sender 50M == Receiver 50M -> BALANCED
        $m1 = $this->service->matchIntercompanyTransactions('ENT-HOLDING', 'ENT-SUBSIDIARY-A', 50000000.0, 50000000.0);
        $this->assertSame('BALANCED', $m1->elimination_status);
        $this->assertEquals(0.00, (float) $m1->variance_idr);

        // 2. Mismatched IC trade: Sender 50M != Receiver 48M -> MISMATCHED
        $m2 = $this->service->matchIntercompanyTransactions('ENT-HOLDING', 'ENT-SUBSIDIARY-B', 50000000.0, 48000000.0);
        $this->assertSame('MISMATCHED', $m2->elimination_status);
        $this->assertEquals(2000000.00, (float) $m2->variance_idr);
    }

    /**
     * (d) Audit status healthy with 0 discrepancies (when mismatches resolved).
     */
    public function test_finance_close_audit(): void
    {
        DB::table('fin_intercompany_matchings')->truncate();
        $this->service->matchIntercompanyTransactions('ENT-HOLDING', 'ENT-SUBSIDIARY-A', 1000000.0, 1000000.0);

        $audit = $this->service->audit();
        $this->assertSame('HEALTHY', $audit['status']);
        $this->assertSame(0, $audit['discrepancy_count']);
    }
}
