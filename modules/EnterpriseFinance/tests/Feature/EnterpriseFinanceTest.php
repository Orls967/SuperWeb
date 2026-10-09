<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\EnterpriseFinance\Application\Services\EnterpriseFinanceService;
use Tests\TestCase;

class EnterpriseFinanceTest extends TestCase
{
    use RefreshDatabase;

    protected EnterpriseFinanceService $financeService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->financeService = app(EnterpriseFinanceService::class);
    }

    public function test_54_1_budget_allocation_and_hard_stop_enforcement(): void
    {
        $budget = $this->financeService->allocateBudget(
            fiscalYear: '2026',
            costCenter: 'CC-IT-INFRA',
            accountCode: 'exp:cloud_services:IDR',
            allocatedIdr: 500_000_000,
            controlType: 'HARD_STOP'
        );

        $this->assertDatabaseHas('ef_budgets', [
            'fiscal_year' => '2026',
            'cost_center_code' => 'CC-IT-INFRA',
            'allocated_amount_idr' => 500_000_000,
            'control_type' => 'HARD_STOP',
        ]);

        // Encumber within budget: 200,000,000 IDR
        $budget = $this->financeService->encumberBudget($budget, 200_000_000);
        $this->assertEquals(200_000_000, $budget->encumbered_amount_idr);

        // Attempt encumbrance exceeding budget (400,000,000 IDR > remaining 300jt)
        $this->expectException(InvalidArgumentException::class);
        $this->financeService->encumberBudget($budget, 400_000_000);
    }

    public function test_54_1_budget_realization(): void
    {
        $budget = $this->financeService->allocateBudget(
            fiscalYear: '2026',
            costCenter: 'CC-MKT',
            accountCode: 'exp:marketing:IDR',
            allocatedIdr: 300_000_000,
            controlType: 'HARD_STOP'
        );

        $this->financeService->encumberBudget($budget, 100_000_000);
        $budget = $this->financeService->realizeSpend($budget->fresh(), 100_000_000);

        $this->assertEquals(0, $budget->encumbered_amount_idr);
        $this->assertEquals(100_000_000, $budget->spent_amount_idr);
    }

    public function test_54_3_tax_summary_and_ppn_reconciliation(): void
    {
        // PPN Summary: DPP 1M, Output Tax 110jt, Input Tax 70jt -> Payable 40jt
        $summary = $this->financeService->recordTaxSummary(
            period: '2026-10',
            taxType: 'PPN',
            taxBaseIdr: 1_000_000_000,
            inputTaxIdr: 70_000_000,
            outputTaxIdr: 110_000_000
        );

        $this->assertEquals(40_000_000, $summary->payable_or_refundable_idr);
        $this->assertDatabaseHas('ef_tax_summaries', [
            'period' => '2026-10',
            'tax_type' => 'PPN',
            'payable_or_refundable_idr' => 40_000_000,
        ]);
    }

    public function test_54_4_segregation_of_duties_detection(): void
    {
        $this->financeService->registerSodRule(
            roleA: 'procurement',
            roleB: 'treasury',
            desc: 'Petugas procurement tidak boleh merangkap pelaksana treasury/pembayaran bank.',
            risk: 'CRITICAL'
        );

        // Case A: User has conflicting roles
        $checkA = $this->financeService->checkSodConflict(['procurement', 'treasury', 'admin']);
        $this->assertTrue($checkA['has_conflict']);
        $this->assertEquals(1, $checkA['conflict_count']);

        // Case B: User has clean non-conflicting roles
        $checkB = $this->financeService->checkSodConflict(['procurement', 'planner']);
        $this->assertFalse($checkB['has_conflict']);
        $this->assertEquals(0, $checkB['conflict_count']);
    }

    public function test_54_6_compliance_deadline_scheduling(): void
    {
        $dl = $this->financeService->scheduleComplianceDeadline(
            title: 'SPT Masa PPN 1111 Pelaporan Oktober 2026',
            authority: 'DJP',
            dueDate: '2026-11-30',
            assignedRole: 'auditor'
        );

        $this->assertDatabaseHas('ef_compliance_deadlines', [
            'id' => $dl->id,
            'regulatory_body' => 'DJP',
            'status' => 'pending',
        ]);
    }

    public function test_54_9_enterprise_finance_audit_and_command(): void
    {
        $this->financeService->allocateBudget(
            fiscalYear: '2026',
            costCenter: 'CC-OPS',
            accountCode: 'exp:operations:IDR',
            allocatedIdr: 100_000_000
        );

        $audit = $this->financeService->auditEnterpriseFinance();
        $this->assertEquals('OK', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);
        $this->assertGreaterThanOrEqual(1, $audit['budget_count']);

        $this->artisan('enterprise:audit')
            ->expectsOutputToContain('Enterprise Finance audit PASSED with 0 discrepancy.')
            ->assertExitCode(0);
    }

    public function test_54_10_http_endpoints(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('enterprise_finance.index'))
            ->assertOk()
            ->assertSee('Finance Grup, Anggaran, Pajak & Tata Kelola');

        $this->actingAs($user)
            ->get(route('enterprise_finance.budgets'))
            ->assertOk()
            ->assertSee('Pengendalian Anggaran');
    }
}
