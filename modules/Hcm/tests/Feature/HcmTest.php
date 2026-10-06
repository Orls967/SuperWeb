<?php

declare(strict_types=1);

namespace Modules\Hcm\tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\Hcm\Application\Services\HcmService;
use Modules\Hcm\Domain\Models\Department;
use Tests\TestCase;

class HcmTest extends TestCase
{
    use RefreshDatabase;

    protected HcmService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(HcmService::class);
    }

    public function test_can_register_employee_and_generate_payroll(): void
    {
        $dept = Department::create([
            'code' => 'ENG',
            'name' => 'Engineering',
            'is_active' => true,
        ]);

        $employee = $this->service->registerEmployee([
            'employee_number' => 'EMP-001',
            'name' => 'Budi Santoso',
            'nik' => '3171012345670001',
            'email' => 'budi@autoserve.test',
            'department_id' => $dept->id,
            'position' => 'Senior Engineer',
            'employment_type' => 'PKWTT',
            'basic_salary_idr' => 12000000,
            'allowances_idr' => 3000000,
            'bank_name' => 'Bank Mandiri',
            'bank_account_number' => '1230009876543',
        ]);

        $this->assertDatabaseHas('hcm_employees', [
            'id' => $employee->id,
            'employee_number' => 'EMP-001',
            'basic_salary_idr' => 12000000,
        ]);

        $payroll = $this->service->generatePayroll($employee, '2026-10');

        $this->assertSame(15000000, $payroll->gross_salary_idr);
        $this->assertSame($payroll->gross_salary_idr - $payroll->deductions_idr, $payroll->net_salary_idr);
        $this->assertGreaterThan(0, $payroll->pph21_idr);
    }

    public function test_can_allocate_labor_cost_to_production(): void
    {
        $employee = $this->service->registerEmployee([
            'employee_number' => 'EMP-002',
            'name' => 'Agus Operator',
            'position' => 'Line Operator',
            'basic_salary_idr' => 6000000,
            'allowances_idr' => 1000000,
        ]);

        $payroll = $this->service->generatePayroll($employee, '2026-10');
        $allocation = $this->service->allocateLaborToProduction($payroll, 'MPO/JKT/2026/001', 40.0, 50000);

        $this->assertSame(2000000, $allocation->allocated_cost_idr);
        $this->assertDatabaseHas('hcm_production_labor_allocations', [
            'id' => $allocation->id,
            'work_order_ref' => 'MPO/JKT/2026/001',
        ]);
    }

    public function test_hcm_audit_passes_with_zero_discrepancy(): void
    {
        $employee = $this->service->registerEmployee([
            'employee_number' => 'EMP-003',
            'name' => 'Dewi Finance',
            'basic_salary_idr' => 8000000,
            'allowances_idr' => 2000000,
        ]);

        $this->service->generatePayroll($employee, '2026-10');

        $exitCode = Artisan::call('hcm:audit');
        $this->assertSame(0, $exitCode);
    }

    public function test_hcm_web_index_accessible(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('hcm.index'));
        $response->assertStatus(200);
        $response->assertSee('Human Capital Management');
    }
}
