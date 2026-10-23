<?php

declare(strict_types=1);

namespace Modules\Contract\tests\Feature;

use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Contract\Application\Actions\AssessContractSlaBreachPenaltyAction;
use Modules\Contract\Application\Actions\CreateMasterContractWithHashAction;
use Modules\Contract\Application\Actions\ExecuteContractMilestoneBillingAction;
use Modules\Contract\Domain\Enums\MilestoneStatus;
use Modules\Contract\Domain\Enums\PaymentScheduleStatus;
use Modules\Contract\Domain\Models\Contract;
use Modules\Contract\Domain\Models\ContractMilestone;
use Modules\Contract\Domain\Models\PaymentSchedule;
use Modules\Contract\Domain\Models\PenaltyRule;
use Tests\TestCase;

class ContractMilestoneAndPenaltyBillingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
    }

    private function createSampleContract(int $value = 500000000, int $retention = 5): Contract
    {
        $legalEntityId = (string) Str::uuid();
        DB::table('pty_legal_entities')->insert([
            'id' => $legalEntityId,
            'name' => 'PT EPC Nusantara',
            'entity_type' => 'company',
            'functional_currency' => 'IDR',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return app(CreateMasterContractWithHashAction::class)->execute([
            'legal_entity_id' => $legalEntityId,
            'title' => 'EPC Plant Construction Master Contract',
            'contract_type' => 'service',
            'total_value_idr' => $value,
            'retention_percent' => $retention,
        ]);
    }

    public function test_1_execute_contract_milestone_billing_and_ledger(): void
    {
        $contract = $this->createSampleContract();

        $milestone = ContractMilestone::create([
            'id' => (string) Str::uuid(),
            'contract_id' => $contract->id,
            'title' => 'Civil Foundation Completion',
            'due_date' => now()->toDateString(),
            'status' => MilestoneStatus::InProgress,
            'amount_idr' => 100000000,
        ]);

        $action = app(ExecuteContractMilestoneBillingAction::class);
        $schedule = $action->execute([
            'contract_id' => $contract->id,
            'milestone_id' => $milestone->id,
            'billing_amount_idr' => 100000000,
        ]);

        $this->assertInstanceOf(PaymentSchedule::class, $schedule);
        $this->assertEquals(PaymentScheduleStatus::Paid, $schedule->status);
        $this->assertSame(100000000, $schedule->paid_amount_idr);
        $this->assertEquals(MilestoneStatus::Completed, $milestone->fresh()->status);

        $this->assertDatabaseHas('bank_ledger_transactions', [
            'type' => 'ctr_payment',
            'reference_id' => $schedule->id,
        ]);
    }

    public function test_2_execute_term_billing_without_milestone(): void
    {
        $contract = $this->createSampleContract();
        $action = app(ExecuteContractMilestoneBillingAction::class);

        $schedule = $action->execute([
            'contract_id' => $contract->id,
            'billing_amount_idr' => 50000000,
            'notes' => 'Advance Term payment',
        ]);

        $this->assertSame(50000000, $schedule->paid_amount_idr);
        $this->assertNull($schedule->milestone_id);
        $this->assertSame(50000000, $contract->fresh()->used_value_idr);
    }

    public function test_3_retention_calculation_deduction(): void
    {
        $contract = $this->createSampleContract(retention: 10);
        $action = app(ExecuteContractMilestoneBillingAction::class);

        $schedule = $action->execute([
            'contract_id' => $contract->id,
            'billing_amount_idr' => 100000000,
            'retention_percent' => 10,
        ]);

        // 10% of 100M = 10M retention withheld
        $this->assertSame(10000000, $schedule->retention_amount_idr);
        $this->assertSame(90000000, $schedule->netAmount());
    }

    public function test_4_assess_sla_breach_penalty_with_rule(): void
    {
        $contract = $this->createSampleContract();

        $rule = PenaltyRule::create([
            'id' => (string) Str::uuid(),
            'contract_id' => $contract->id,
            'name' => 'Fixed daily penalty after 3 grace days',
            'unit' => PenaltyRule::UNIT_PER_DAY_FIXED,
            'value' => 500000, // 500,000 IDR / day
            'cap_amount_idr' => 50000000,
            'grace_days' => 3,
            'is_active' => true,
        ]);

        $action = app(AssessContractSlaBreachPenaltyAction::class);

        // 8 days late - 3 grace days = 5 effective days * 500k = 2,500,000 IDR
        $result = $action->execute([
            'contract_id' => $contract->id,
            'days_late' => 8,
        ]);

        $this->assertSame(2500000, $result['penalty_amount_idr']);
        $this->assertSame('Fixed daily penalty after 3 grace days', $result['rule_applied']);
    }

    public function test_5_assess_sla_breach_penalty_grace_period(): void
    {
        $contract = $this->createSampleContract();

        PenaltyRule::create([
            'id' => (string) Str::uuid(),
            'contract_id' => $contract->id,
            'name' => 'Grace period rule',
            'unit' => PenaltyRule::UNIT_PER_DAY_FIXED,
            'value' => 1000000,
            'cap_amount_idr' => 20000000,
            'grace_days' => 5,
            'is_active' => true,
        ]);

        $action = app(AssessContractSlaBreachPenaltyAction::class);

        // 3 days late <= 5 grace days => 0 penalty
        $result = $action->execute([
            'contract_id' => $contract->id,
            'days_late' => 3,
        ]);

        $this->assertSame(0, $result['penalty_amount_idr']);
    }

    public function test_6_assess_sla_breach_penalty_ledger_posting(): void
    {
        $contract = $this->createSampleContract();

        PenaltyRule::create([
            'id' => (string) Str::uuid(),
            'contract_id' => $contract->id,
            'name' => 'Late SLA rule',
            'unit' => PenaltyRule::UNIT_PER_DAY_FIXED,
            'value' => 2000000,
            'cap_amount_idr' => 50000000,
            'grace_days' => 0,
            'is_active' => true,
        ]);

        $action = app(AssessContractSlaBreachPenaltyAction::class);
        $result = $action->execute([
            'contract_id' => $contract->id,
            'days_late' => 5,
        ]);

        $this->assertSame(10000000, $result['penalty_amount_idr']);

        $this->assertDatabaseHas('bank_ledger_transactions', [
            'type' => 'ctr_penalty',
            'reference_id' => $contract->contract_number,
        ]);
    }
}
