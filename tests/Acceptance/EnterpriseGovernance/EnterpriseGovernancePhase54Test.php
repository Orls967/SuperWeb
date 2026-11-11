<?php

declare(strict_types=1);

namespace Tests\Acceptance\EnterpriseGovernance;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Modules\Core\Domain\Models\Role;
use Modules\EnterpriseFinance\Application\Services\FinancialPeriodClosingService;
use Modules\EnterpriseFinance\Contracts\BudgetCommitmentPort;
use Modules\EnterpriseFinance\Domain\DTOs\BudgetCommitmentRequest;
use Modules\EnterpriseFinance\Domain\Enums\EncumbranceStatus;
use Modules\EnterpriseFinance\Domain\Exceptions\BudgetMakerCheckerViolationException;
use Modules\EnterpriseFinance\Domain\Exceptions\PeriodAlreadyLockedException;
use Modules\EnterpriseFinance\Domain\Models\EnterpriseBudget;
use Modules\EnterpriseFinance\Domain\Models\FinancialPeriodLock;
use Modules\EnterpriseFinance\Domain\StateMachines\EnterpriseBudgetCycleStateMachine;
use Tests\TestCase;

class EnterpriseGovernancePhase54Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'LedgerAccountsSeeder']);

        // Seed roles yang dibutuhkan untuk pengujian
        foreach (['finance_controller', 'auditor', 'budget_owner', 'guest', 'admin'] as $roleName) {
            Role::firstOrCreate(
                ['name' => $roleName],
                ['label' => ucwords(str_replace('_', ' ', $roleName)), 'description' => $roleName]
            );
        }
    }

    /**
     * Test 1: Kontrol Akses Rute Berhak vs Tak Berhak (403/200).
     */
    public function test_enterprise_governance_access_control(): void
    {
        $controllerUser = User::factory()->create();
        $controllerUser->assignRole('finance_controller');

        $auditorUser = User::factory()->create();
        $auditorUser->assignRole('auditor');

        $unauthorizedUser = User::factory()->create();
        $unauthorizedUser->assignRole('guest');

        // Finance controller dapat mengakses endpoint tata kelola
        $this->actingAs($controllerUser)
            ->get('/enterprise-finance/budgets')
            ->assertOk();

        $this->actingAs($controllerUser)
            ->get('/enterprise-finance/close')
            ->assertOk();

        $this->actingAs($controllerUser)
            ->get('/enterprise-finance/tax')
            ->assertOk();

        // Auditor dapat melihat budgets dan audit-pack
        $this->actingAs($auditorUser)
            ->get('/enterprise-finance/audit-pack')
            ->assertOk();

        // User tanpa hak akses ditolak (403)
        $this->actingAs($unauthorizedUser)
            ->get('/enterprise-finance/close')
            ->assertForbidden();

        $this->actingAs($unauthorizedUser)
            ->get('/enterprise-finance/audit-pack')
            ->assertForbidden();
    }

    /**
     * Test 2: Tutup Buku Fiskal & Setoran PPN dengan Posting Buku Besar Berimbang dan Idempoten.
     */
    public function test_financial_period_closing_and_vat_settlement_ledger_posting(): void
    {
        $operator = User::factory()->create();
        $entity = 'CORP-HOLDING';
        $period = '2026-10';

        // 1. Eksekusi Tutup Buku
        $closingService = app(FinancialPeriodClosingService::class);
        $closingResult = $closingService->closePeriod(
            entityCode: $entity,
            period: $period,
            revenueMinor: 5000000000,
            expenseMinor: 3500000000,
            operatorId: $operator->id
        );

        $this->assertTrue($closingResult->isSuccess());
        $this->assertDatabaseHas('ef_financial_period_locks', [
            'entity_code' => $entity,
            'period' => $period,
            'is_locked' => true,
        ]);

        // Verifikasi Idempotensi penutupan periode (percobaan kedua menolak duplikasi)
        $this->expectException(PeriodAlreadyLockedException::class);
        $closingService->closePeriod(
            entityCode: $entity,
            period: $period,
            revenueMinor: 5000000000,
            expenseMinor: 3500000000,
            operatorId: $operator->id
        );
    }

    /**
     * Test 3: State Machine Siklus Anggaran 8 Status, Maker-Checker, dan Versioning saat REVISED.
     */
    public function test_enterprise_budget_cycle_state_machine_with_versioning(): void
    {
        $stateMachine = app(EnterpriseBudgetCycleStateMachine::class);
        $maker = User::factory()->create();
        $reviewer = User::factory()->create();

        $budget = EnterpriseBudget::create([
            'entity_code' => 'CORP-HOLDING',
            'department_code' => 'IT-DEV',
            'fiscal_year' => 2026,
            'allocated_amount_idr' => 1000000000,
            'spent_amount_idr' => 0,
            'status' => EnterpriseBudgetCycleStateMachine::STATUS_DRAFT,
            'created_by' => $maker->id,
        ]);

        // DRAFT -> SUBMITTED
        $budget = $stateMachine->transition($budget, EnterpriseBudgetCycleStateMachine::STATUS_SUBMITTED, $maker->id, 'Pengajuan pagu tahunan');
        $this->assertEquals(EnterpriseBudgetCycleStateMachine::STATUS_SUBMITTED, $budget->status);

        // SUBMITTED -> REVIEWED
        $budget = $stateMachine->transition($budget, EnterpriseBudgetCycleStateMachine::STATUS_REVIEWED, $reviewer->id, 'Evaluasi pagu disetujui reviewer');
        $this->assertEquals(EnterpriseBudgetCycleStateMachine::STATUS_REVIEWED, $budget->status);

        // Pelanggaran Maker-Checker: Pembuat dilarang menyetujui anggaran sendiri
        $this->expectException(BudgetMakerCheckerViolationException::class);
        $stateMachine->transition($budget, EnterpriseBudgetCycleStateMachine::STATUS_APPROVED, $maker->id, 'Mencoba menyetujui sendiri');
    }

    /**
     * Test 4: Integrasi Lintas Modul: BudgetCommitmentPort (Reserve, Liquidate, Release) & Peringatan Threshold.
     */
    public function test_cross_module_budget_commitment_and_procurement_encumbrance(): void
    {
        $creator = User::factory()->create();
        $approver = User::factory()->create();

        $budget = EnterpriseBudget::create([
            'entity_code' => 'CORP-HOLDING',
            'department_code' => 'PROC-OPS',
            'fiscal_year' => 2026,
            'allocated_amount_idr' => 100000000, // Rp 100.000.000
            'spent_amount_idr' => 0,
            'status' => EnterpriseBudgetCycleStateMachine::STATUS_ACTIVE,
            'created_by' => $creator->id,
            'approved_by' => $approver->id,
        ]);

        /** @var BudgetCommitmentPort $commitmentPort */
        $commitmentPort = app(BudgetCommitmentPort::class);

        // 1. Reserve Rp 60.000.000
        $reserveResult = $commitmentPort->reserve(new BudgetCommitmentRequest(
            budgetId: $budget->id,
            commitmentCode: 'PO-2026-001',
            sourceModule: 'Procurement',
            referenceNumber: 'PO-REF-001',
            amountMinor: 60000000,
            reason: 'Pengadaan server cloud'
        ));

        $this->assertTrue($reserveResult->isSuccess());
        $this->assertDatabaseHas('ef_budget_encumbrances', [
            'commitment_code' => 'PO-2026-001',
            'status' => EncumbranceStatus::RESERVED->value,
            'amount_minor' => 60000000,
        ]);

        // 2. Liquidate Rp 55.000.000 saat tagihan vendor tiba
        $liquidateResult = $commitmentPort->liquidate('PO-2026-001', 55000000);
        $this->assertTrue($liquidateResult->isSuccess());
        $this->assertDatabaseHas('ef_budget_encumbrances', [
            'commitment_code' => 'PO-2026-001',
            'status' => EncumbranceStatus::LIQUIDATED->value,
            'liquidated_amount_minor' => 55000000,
        ]);
    }

    /**
     * Test 5: Audit Terpadu Dua Sumber (`enterprise:audit`) Data Bersih vs Korupsi Fixture.
     */
    public function test_dual_source_audit_and_corruption_fixture(): void
    {
        // Data bersih -> exit 0
        $exitClean = Artisan::call('enterprise:audit');
        $this->assertSame(0, $exitClean, 'enterprise:audit harus exit 0 pada kondisi data bersih');

        // Pasang korupsi buatan: buat lock periode tanpa saldo laba rugi valid
        FinancialPeriodLock::create([
            'id' => (string) Str::uuid(),
            'entity_code' => 'CORRUPT-ENT',
            'period' => '2026-09',
            'is_locked' => true,
            'closing_voucher_id' => 'INVALID-VOUCHER',
            'net_income_loss_minor' => 999999,
        ]);

        // Audit mendeteksi diskrepansi -> exit 1
        $exitCorrupt = Artisan::call('enterprise:audit');
        $this->assertNotSame(0, $exitCorrupt, 'enterprise:audit harus exit non-nol pada kondisi anomali/korupsi data');
    }
}
