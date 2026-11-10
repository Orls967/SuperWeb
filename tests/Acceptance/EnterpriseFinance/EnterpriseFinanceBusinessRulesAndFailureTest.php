<?php

declare(strict_types=1);

namespace Tests\Acceptance\EnterpriseFinance;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\EnterpriseFinance\Application\Services\EnterpriseFinanceService;
use Modules\EnterpriseFinance\Domain\Exceptions\BudgetInvalidTransitionException;
use Modules\EnterpriseFinance\Domain\Exceptions\BudgetMakerCheckerViolationException;
use Modules\EnterpriseFinance\Domain\Models\EnterpriseBudget;
use Modules\EnterpriseFinance\Domain\StateMachines\EnterpriseBudgetCycleStateMachine;
use Tests\TestCase;

class EnterpriseFinanceBusinessRulesAndFailureTest extends TestCase
{
    use RefreshDatabase;

    private EnterpriseFinanceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EnterpriseFinanceService::class);
    }

    public function test_budget_hard_stop_rejects_overspend(): void
    {
        $budget = $this->service->allocateBudget(
            fiscalYear: '2026',
            costCenter: 'MARKETING',
            accountCode: '6101-ADS',
            allocatedIdr: 100_000_000,
            controlType: 'HARD_STOP'
        );

        // Belanja melebihi pagu melempar exception
        $this->expectException(InvalidArgumentException::class);
        $this->service->encumberBudget($budget, 150_000_000);
    }

    public function test_budget_state_machine_invalid_transition_throws_exception(): void
    {
        $creator = User::factory()->create(['role' => 'admin']);
        $operator = User::factory()->create(['role' => 'treasury']);

        $budget = EnterpriseBudget::create([
            'budget_code' => 'BGT-2026-IT',
            'fiscal_year' => '2026',
            'cost_center_code' => 'IT_INFRA',
            'account_code' => '6201-CLOUD',
            'allocated_amount_idr' => 200_000_000,
            'encumbered_amount_idr' => 0,
            'spent_amount_idr' => 0,
            'control_type' => 'HARD_STOP',
            'status' => 'DRAFT',
            'created_by' => $creator->id,
        ]);

        $sm = app(EnterpriseBudgetCycleStateMachine::class);

        // Transisi lompat dari DRAFT langsung ke APPROVED dilarang
        $this->expectException(BudgetInvalidTransitionException::class);
        $sm->transition($budget, 'APPROVED', operatorId: $operator->id);
    }

    public function test_budget_four_eyes_maker_checker_violation_guard(): void
    {
        $creator = User::factory()->create(['role' => 'admin']);

        $budget = EnterpriseBudget::create([
            'budget_code' => 'BGT-2026-LEGAL',
            'fiscal_year' => '2026',
            'cost_center_code' => 'LEGAL',
            'account_code' => '6301-FEES',
            'allocated_amount_idr' => 150_000_000,
            'encumbered_amount_idr' => 0,
            'spent_amount_idr' => 0,
            'control_type' => 'HARD_STOP',
            'status' => 'REVIEWED',
            'created_by' => $creator->id,
        ]);

        $sm = app(EnterpriseBudgetCycleStateMachine::class);

        // Pembuat usulan dilarang mengesahkan sendiri (Four-Eyes Principle)
        $this->expectException(BudgetMakerCheckerViolationException::class);
        $sm->transition($budget, 'APPROVED', operatorId: $creator->id);
    }

    public function test_sod_conflict_detection_detects_forbidden_roles(): void
    {
        $this->service->registerSodRule('procurement_officer', 'payment_disburser', 'Pemisahan pengadaan dan pembayaran kas', 'CRITICAL');

        $resultWithConflict = $this->service->checkSodConflict(['procurement_officer', 'payment_disburser']);
        $this->assertTrue($resultWithConflict['has_conflict']);
        $this->assertSame(1, $resultWithConflict['conflict_count']);

        $resultNoConflict = $this->service->checkSodConflict(['procurement_officer', 'auditor']);
        $this->assertFalse($resultNoConflict['has_conflict']);
    }
}
