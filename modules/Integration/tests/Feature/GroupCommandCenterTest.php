<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Integration\Application\Services\GroupCommandCenterService;
use Tests\TestCase;

/**
 * Fase 190 — Integrasi 30 Lini E: Group Command Center & Daily Operations Tests
 *
 * Covers:
 *  (a) duplicate operational alerts merged by deduplication fingerprint
 *  (b) SLA escalation increases severity level deterministically
 *  (c) close-day revenue reconciles exactly with general ledger (variance == 0)
 *  (d) commandcenter:audit = 0 discrepancy
 */
class GroupCommandCenterTest extends TestCase
{
    use RefreshDatabase;

    protected GroupCommandCenterService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(GroupCommandCenterService::class);
    }

    /**
     * (a) Duplicate operational alerts deduplicated.
     */
    public function test_alert_fingerprint_deduplication(): void
    {
        $fingerprint = 'FP-PORT-BERTH-01-DELAY-20261008';

        // 1. Initial alert
        $a1 = $this->service->triageAlert($fingerprint, 'L27', 'SAFETY', 'PORT_SUPERVISOR', 30);
        $this->assertSame('OPEN', $a1->status);

        // 2. Duplicate alert merges
        $a2 = $this->service->triageAlert($fingerprint, 'L27', 'SAFETY', 'PORT_SUPERVISOR', 30);
        $this->assertSame($a1->alert_code, $a2->alert_code);

        $count = DB::table('cmd_operational_alerts')->count();
        $this->assertSame(1, $count);
    }

    /**
     * (b) Alert escalation progression.
     */
    public function test_alert_sla_escalation(): void
    {
        $alert = $this->service->triageAlert('FP-HOSP-OXYGEN-LOW-01', 'L18', 'SAFETY', 'CLINICAL_LEAD', 15);
        $this->assertSame('LEVEL_1', $alert->escalation_level);

        $escalated = $this->service->escalateAlert($alert->alert_code);
        $this->assertSame('LEVEL_2_DIRECTOR', $escalated->escalation_level);
        $this->assertSame('ESCALATED', $escalated->status);
    }

    /**
     * (c) Cross-line end-of-day revenue reconciliation to general ledger.
     */
    public function test_daily_eod_close_ledger_parity(): void
    {
        $today = Carbon::today();

        // 1. Matched EOD: Rp 125,000,000 revenue & posted ledger -> SUCCESS
        $close = $this->service->executeDailyClose($today, 'L13', 125000000.0, 125000000.0);
        $this->assertEquals(0.00, (float) $close->eod_variance_idr);

        // 2. Discrepancy between operational revenue and ledger -> BLOCKED
        try {
            $this->service->executeDailyClose($today, 'L14', 80000000.0, 79500000.0);
            $this->fail('Expected exception for EOD close ledger discrepancy.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('EOD close discrepancy', $e->getMessage());
        }
    }

    /**
     * (d) Audit status healthy with 0 discrepancies.
     */
    public function test_command_center_audit(): void
    {
        $audit = $this->service->audit();
        $this->assertSame('HEALTHY', $audit['status']);
        $this->assertSame(0, $audit['discrepancy_count']);
    }
}
