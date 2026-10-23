<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\EnterpriseFinance\Application\Actions\ConsolidateGroupMultiEntityTrialBalanceAction;
use Modules\EnterpriseFinance\Application\Actions\EnforceDepartmentBudgetGuardrailAction;
use Modules\EnterpriseFinance\Application\Actions\MonitorDebtCovenantRatiosAction;
use Modules\EnterpriseFinance\Domain\Models\EnterpriseBudget;
use Tests\TestCase;

class EnterpriseFinanceOperationsSliceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected EnterpriseBudget $budget;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['role' => 'admin']);

        $this->budget = EnterpriseBudget::create([
            'budget_code' => 'BUD-2026-RND-001',
            'fiscal_year' => '2026',
            'cost_center_code' => 'CC-RND',
            'account_code' => '6100-RND-EXPENSE',
            'allocated_amount_idr' => 1000000000,
            'encumbered_amount_idr' => 100000000,
            'spent_amount_idr' => 300000000,
            'control_type' => 'HARD_STOP',
        ]);
    }

    public function test_consolidate_group_multi_entity_trial_balance_totals(): void
    {
        $action = app(ConsolidateGroupMultiEntityTrialBalanceAction::class);

        $res = $action->execute('2026-Q3', [
            'ENTITY_MANUFACTURING' => [
                'revenue_minor' => 5000000000,
                'ebitda_minor' => 1200000000,
            ],
            'ENTITY_DISTRIBUTION' => [
                'revenue_minor' => 3000000000,
                'ebitda_minor' => 450000000,
            ],
            'ENTITY_RETAIL' => [
                'revenue_minor' => 2000000000,
                'ebitda_minor' => 350000000,
            ],
        ]);

        $this->assertNotNull($res['report']->id);
        $this->assertSame(10000000000, $res['total_revenue_idr']);
        $this->assertSame(2000000000, $res['total_ebitda_idr']);
        $this->assertSame(3, $res['entity_count']);
    }

    public function test_enforce_department_budget_guardrail_successful_spend(): void
    {
        $action = app(EnforceDepartmentBudgetGuardrailAction::class);

        // Allocated: 1,000,000,000. Spent: 300,000,000. Encumbered: 100,000,000.
        // Available: 600,000,000. Requested: 200,000,000 -> Allowed.
        $res = $action->execute($this->budget, 200000000, 'spend');

        $this->assertSame(500000000, $res['new_spent_idr']);
        $this->assertSame(400000000, $res['remaining_available_idr']);
        $this->assertSame(500000000, $this->budget->fresh()->spent_amount_idr);
    }

    public function test_enforce_department_budget_guardrail_hard_stop_rejection(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $action = app(EnforceDepartmentBudgetGuardrailAction::class);

        // Available is 600,000,000. Requested is 700,000,000 with HARD_STOP -> Throws exception
        $action->execute($this->budget, 700000000, 'spend');
    }

    public function test_enforce_department_budget_guardrail_encumbrance_tracking(): void
    {
        $action = app(EnforceDepartmentBudgetGuardrailAction::class);

        $res = $action->execute($this->budget, 150000000, 'encumber');

        $this->assertSame(250000000, $res['encumbered_idr']);
        $this->assertSame(450000000, $res['remaining_available_idr']);
        $this->assertSame(250000000, $this->budget->fresh()->encumbered_amount_idr);
    }

    public function test_monitor_debt_covenant_ratios_compliant_and_breach(): void
    {
        $action = app(MonitorDebtCovenantRatiosAction::class);

        // 1. Compliant scenario: Debt 10B, Equity 10B -> DER = 1.0 (limit <= 2.5)
        // EBITDA 4B, Interest 1B -> ICR = 4.0 (limit >= 3.0)
        $resCompliant = $action->execute(10000000000, 10000000000, 4000000000, 1000000000);
        $this->assertSame(1.0, $resCompliant['debt_to_equity_ratio']);
        $this->assertSame(4.0, $resCompliant['interest_coverage_ratio']);
        $this->assertSame('COMPLIANT', $resCompliant['covenant_status']);
        $this->assertTrue($resCompliant['is_compliant']);

        // 2. Breach scenario: Debt 30B, Equity 10B -> DER = 3.0 (> 2.5) -> BREACH
        $resBreach = $action->execute(30000000000, 10000000000, 2000000000, 1000000000);
        $this->assertSame(3.0, $resBreach['debt_to_equity_ratio']);
        $this->assertSame('BREACH', $resBreach['covenant_status']);
        $this->assertFalse($resBreach['is_compliant']);
    }

    public function test_enterprise_finance_governance_controller_endpoints(): void
    {
        $this->actingAs($this->user);

        // 1. Dashboard
        $respDash = $this->get('/enterprise-finance/governance');
        $respDash->assertOk();

        // 2. Consolidate Trial Balance API
        $respConsolidate = $this->postJson('/enterprise-finance/governance/trial-balance/consolidate', [
            'period_quarter' => '2026-Q4',
            'entity_balances' => [
                'AGRI_SUBHOLDING' => [
                    'revenue_minor' => 4000000000,
                    'ebitda_minor' => 800000000,
                ],
                'ENERGY_SUBHOLDING' => [
                    'revenue_minor' => 6000000000,
                    'ebitda_minor' => 1500000000,
                ],
            ],
        ]);
        $respConsolidate->assertStatus(201);
        $this->assertSame(10000000000, $respConsolidate->json('data.total_revenue_idr'));

        // 3. Enforce Budget API
        $respBudget = $this->postJson("/enterprise-finance/governance/budget/{$this->budget->id}/enforce", [
            'requested_amount_idr' => 50000000,
            'mode' => 'spend',
        ]);
        $respBudget->assertOk();
        $this->assertSame(350000000, $respBudget->json('data.new_spent_idr'));

        // 4. Monitor Covenants API
        $respCov = $this->postJson('/enterprise-finance/governance/covenants/monitor', [
            'total_debt_idr' => 15000000000,
            'total_equity_idr' => 10000000000,
            'ebitda_idr' => 5000000000,
            'annual_interest_expense_idr' => 1000000000,
        ]);
        $respCov->assertOk();
        $this->assertSame('COMPLIANT', $respCov->json('data.covenant_status'));
    }
}
