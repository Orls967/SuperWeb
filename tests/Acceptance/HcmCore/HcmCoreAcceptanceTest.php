<?php

declare(strict_types=1);

namespace Tests\Acceptance\HcmCore;

use App\Models\User;
use App\Quality\Audit\Fixtures\HcmAuditCorruptionFixture;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Modules\Hcm\Application\Actions\ManageOrgStructureAndJobGradeAction;
use Modules\Hcm\Application\Services\HcmPayrollCalculationService;
use Modules\Hcm\Application\Services\HcmRosterOptimizationService;
use Modules\Hcm\Domain\Models\Department;
use Modules\Hcm\Domain\Models\Employee;
use Modules\Hcm\Domain\Models\Payroll;
use Tests\TestCase;

/**
 * Acceptance Test Fase 58 — HCM Inti (D58-1..D58-6)
 * Maksimal 6 test sesuai L.2.
 */
class HcmCoreAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    private User $hcmManager;

    private User $employeeUser;

    private User $otherUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);

        $this->hcmManager = User::factory()->create(['role' => 'hcm_manager']);
        $this->employeeUser = User::factory()->create(['role' => 'employee']);
        $this->otherUser = User::factory()->create(['role' => 'employee']);
    }

    /**
     * 1. Akses RBAC: rute /hcm (hcm_manager 200, employee/guest 403)
     */
    public function test_58_1_rbac_access_matrix(): void
    {
        $this->actingAs($this->hcmManager)->get('/hcm')->assertOk();
        $this->actingAs($this->employeeUser)->get('/hcm')->assertForbidden();
        $this->get('/hcm')->assertForbidden();
    }

    /**
     * 2. Alur utama end-to-end + posting ledger (nilai persis D58-4 & D58-5) + idempotensi run-payroll
     */
    public function test_58_2_e2e_payroll_calculation_posting_and_idempotency(): void
    {
        $dept = Department::create([
            'code' => 'OPS-01',
            'name' => 'Operasional Pabrik',
        ]);

        $employee = Employee::create([
            'user_id' => $this->employeeUser->id,
            'employee_number' => 'EMP-1001',
            'name' => 'Budi Santoso',
            'nik' => '3171012345670001',
            'department_id' => $dept->id,
            'position' => 'Operator Senior',
            'basic_salary_idr' => 10_000_000,
            'allowances_idr' => 0,
            'bank_name' => 'Bank Mandiri',
            'bank_account_number' => '1234567890',
            'is_active' => true,
        ]);

        $payrollService = app(HcmPayrollCalculationService::class);
        $res = $payrollService->calculatePayroll($employee, '2026-10', 'hcm:pay:run:2026-10:EMP-1001');

        $this->assertSame(10_000_000, $res['gross_salary_idr']);
        $this->assertSame(10_454_000, $res['taxable_gross_idr']); // Bruto PPh21: Gaji + JKK(24k) + JKM(30k) + BPJS Kes Employer(400k)
        $this->assertSame(522_700, $res['pph21_idr']); // 5% dari 10.454.000
        $this->assertSame(400_000, $res['worker_deduction_idr']); // BPJS Kes(100k) + JHT(200k) + JP(100k)
        $this->assertSame(9_077_300, $res['net_salary_idr']); // 10.000.000 - 522.700 - 400.000
        $this->assertSame(1_024_000, $res['employer_contribution_idr']); // BPJS Kes(400k) + JHT(370k) + JP(200k) + JKK(24k) + JKM(30k)

        // Idempotensi: jalankan lagi periode sama via command hcm:run-payroll
        $this->artisan('hcm:run-payroll', ['period' => '2026-10'])->assertExitCode(0);
        $this->assertSame(1, Payroll::where('employee_id', $employee->id)->where('period', '2026-10')->count());
    }

    /**
     * 3. Aturan bisnis: siklus organisasi ditolak
     */
    public function test_58_3_org_cycle_rejected(): void
    {
        $orgAction = app(ManageOrgStructureAndJobGradeAction::class);
        $d1 = $orgAction->manageDepartment(['code' => 'D1', 'name' => 'Divisi 1']);
        $d2 = $orgAction->manageDepartment(['code' => 'D2', 'name' => 'Divisi 2', 'parent_id' => $d1->id]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Siklus');
        // Mencoba membuat D1 berinduk ke D2 -> siklus D1 -> D2 -> D1
        $orgAction->manageDepartment(['code' => 'D1', 'name' => 'Divisi 1', 'parent_id' => $d2->id]);
    }

    /**
     * 4. IDOR: Policy PayrollPolicy (karyawan hanya bisa melihat slip miliknya sendiri)
     */
    public function test_58_4_anti_idor_payroll_slip_policy(): void
    {
        $employee = Employee::create([
            'user_id' => $this->employeeUser->id,
            'employee_number' => 'EMP-1002',
            'name' => 'Siti Rahma',
            'position' => 'Staff Administrasi',
            'basic_salary_idr' => 8_000_000,
            'allowances_idr' => 0,
            'bank_name' => 'Bank Mandiri',
            'bank_account_number' => '1234567891',
            'is_active' => true,
        ]);

        $payroll = Payroll::create([
            'employee_id' => $employee->id,
            'period' => '2026-10',
            'gross_salary_idr' => 8_000_000,
            'deductions_idr' => 500_000,
            'net_salary_idr' => 7_500_000,
            'pph21_idr' => 200_000,
            'bpjs_tk_idr' => 200_000,
            'bpjs_kes_idr' => 100_000,
            'status' => 'approved',
        ]);

        // Pemilik slip (employeeUser) dan hcmManager diizinkan policy
        $this->assertTrue(Gate::forUser($this->employeeUser)->allows('view', $payroll));
        $this->assertTrue(Gate::forUser($this->hcmManager)->allows('view', $payroll));

        // Karyawan lain (otherUser) ditolak policy
        $this->assertFalse(Gate::forUser($this->otherUser)->allows('view', $payroll));
    }

    /**
     * 5. Vektor Roster Hungarian D58-3
     */
    public function test_58_5_roster_hungarian_assignment_vector(): void
    {
        $rosterService = app(HcmRosterOptimizationService::class);
        $costMatrix = [
            [9, 2, 7, 8],
            [6, 4, 3, 7],
            [5, 8, 1, 8],
            [7, 6, 9, 4],
        ];

        $res = $rosterService->optimizeWeeklyRoster($costMatrix);

        $this->assertSame(13, $res->totalCost);
        $this->assertSame([0 => 1, 1 => 0, 2 => 2, 3 => 3], $res->assignment);
    }

    /**
     * 6. Audit dua sumber: hcm:audit data bersih exit 0 + fixture korupsi exit != 0
     */
    public function test_58_6_hcm_audit_passes_and_fails_on_corruption(): void
    {
        $this->artisan('hcm:audit')->assertExitCode(0);

        (new HcmAuditCorruptionFixture)->corrupt();
        $this->artisan('hcm:audit')->assertExitCode(1);
    }
}
