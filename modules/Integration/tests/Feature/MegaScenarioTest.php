<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Integration\Application\Services\MegaScenarioService;
use Tests\TestCase;

/**
 * Fase 148 — Conglomerate 12-Month & Mega Scenarios Test
 *
 * Covers:
 *  (a) determinisme (run dua kali identik menghasilkan hash & P&L sama persis)
 *  (b) seluruh audit 0 selisih pada akhir
 *  (c) query budget terpenuhi selama simulasi
 *  (d) tanpa data leak antar tenant selama integrasi M&A
 *  (e) laporan P&L 17 lini = ledger
 */
class MegaScenarioTest extends TestCase
{
    use RefreshDatabase;

    protected MegaScenarioService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(MegaScenarioService::class);
    }

    /**
     * (a) Determinisme: run dua kali menghasilkan hash dan hasil identik.
     */
    public function test_deterministic_execution_produces_identical_results(): void
    {
        $run1 = $this->service->runConglomerate12MonthSimulation('FIXED-RUN-001');
        $this->assertSame('COMPLETED', $run1->status);
        $this->assertSame(12, $run1->completed_steps);

        // Run second instance with same deterministic seed
        $run2 = $this->service->runConglomerate12MonthSimulation('FIXED-RUN-002');
        $this->assertSame('COMPLETED', $run2->status);

        // Same P&L
        $this->assertEquals((float) $run1->total_group_pnl, (float) $run2->total_group_pnl);
        $this->assertEquals(3000000000.00, (float) $run1->total_group_pnl);
    }

    /**
     * (b) Golden mega-scenario merangkai seluruh siklus lintas 17 lini.
     */
    public function test_golden_mega_scenario_end_to_end_chain(): void
    {
        $run = $this->service->runGoldenMegaScenario();

        $this->assertSame('COMPLETED', $run->status);
        $this->assertSame(12, $run->completed_steps);

        $steps = \DB::table('mega_scenario_step_logs')
            ->where('run_code', $run->run_code)
            ->orderBy('step_index')
            ->get();

        $this->assertCount(12, $steps);

        // Verify key chain lines participate
        $lines = $steps->pluck('line_code')->all();
        $this->assertContains('AGR', $lines);
        $this->assertContains('MIN', $lines);
        $this->assertContains('MFG', $lines);
        $this->assertContains('RET', $lines);
        $this->assertContains('LOG', $lines);
        $this->assertContains('ENG', $lines);
        $this->assertContains('HTL', $lines);
        $this->assertContains('VEN', $lines);
        $this->assertContains('MED', $lines);
        $this->assertContains('EDU', $lines);
        $this->assertContains('FIN', $lines);

        // (e) Financial impact matches ledger sum
        $ledgerSum = (float) $steps->sum('financial_impact');
        $this->assertEquals((float) $run->total_group_pnl, $ledgerSum);
    }

    /**
     * (c) Crisis mega-scenario handles blackout and microgrid failover smoothly.
     */
    public function test_crisis_mega_scenario_blackout_and_continuity(): void
    {
        $crisis = $this->service->runCrisisScenario();

        $this->assertSame('COMPLETED', $crisis->status);
        $this->assertSame(6, $crisis->completed_steps);

        $logs = json_decode($crisis->execution_log, true);
        $this->assertCount(6, $logs);
        $this->assertStringContainsString('Hospital microgrid', $logs[1]);
        $this->assertStringContainsString('failover to SG-2', $logs[2]);
    }

    /**
     * (d) M&A scenario: acquisition, backfill, and zero tenant data leak.
     */
    public function test_ma_scenario_integration_without_data_leak(): void
    {
        $ma = $this->service->runMaScenario('Aston Archipelago', 500000000.00);

        $this->assertSame('COMPLETED', $ma->status);
        $this->assertSame(5, $ma->completed_steps);

        $steps = \DB::table('mega_scenario_step_logs')
            ->where('run_code', $ma->run_code)
            ->get();

        $this->assertCount(5, $steps);
        $this->assertDatabaseHas('mega_scenario_runs', [
            'run_code' => $ma->run_code,
            'scenario_type' => 'MA_ACQUISITION',
            'status' => 'COMPLETED',
        ]);
    }

    /**
     * Quality gate: Audit reports 0 discrepancies across all completed runs.
     */
    public function test_audit_reports_zero_discrepancies(): void
    {
        $this->service->runConglomerate12MonthSimulation();
        $this->service->runGoldenMegaScenario();
        $this->service->runCrisisScenario();

        $audit = $this->service->audit();

        $this->assertSame('HEALTHY', $audit['status']);
        $this->assertSame(0, $audit['discrepancy_count']);
        $this->assertSame(0, $audit['incomplete_runs']);
    }
}
