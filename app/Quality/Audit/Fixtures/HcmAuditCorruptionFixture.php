<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Quality\Audit\CorruptionFixture;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class HcmAuditCorruptionFixture implements CorruptionFixture
{
    public function command(): string
    {
        return 'hcm:audit';
    }

    public function corrupt(): string
    {
        $employeeId = (string) Str::uuid();

        DB::table('hcm_employees')->insert([
            'id' => $employeeId,
            'employee_number' => 'EMP-999',
            'nik_hash' => hash('sha256', '1234567890123456'),
            'name' => 'Karyawan Uji Korupsi',
            'department_id' => (string) Str::uuid(),
            'position' => 'Staff',
            'employment_type' => 'PKWTT',
            'basic_salary_idr' => 10000000,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('hcm_payrolls')->insert([
            'id' => (string) Str::uuid(),
            'employee_id' => $employeeId,
            'period' => '2026-10',
            'gross_salary_idr' => 10000000,
            'deductions_idr' => 1000000,
            'net_salary_idr' => 5000000, // Seharusnya 9.000.000 (mismatch net_salary)
            'pph21_idr' => 500000,
            'bpjs_tk_idr' => 300000,
            'bpjs_kes_idr' => 200000,
            'status' => 'calculated',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return 'HCM audit FAILED';
    }
}
