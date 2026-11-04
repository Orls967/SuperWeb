<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Security\BlindIndex;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Hcm\Domain\Models\Department;
use Modules\Hcm\Domain\Models\ShiftSchedule;

class HcmCoreT1Seeder extends Seeder
{
    public function run(): void
    {
        $dept = Department::firstOrCreate(
            ['code' => 'OPS-T1'],
            ['name' => 'Divisi Operasional Skala T1', 'is_active' => true]
        );

        $shift = ShiftSchedule::firstOrCreate(
            ['shift_code' => 'S1-PAGI'],
            [
                'name' => 'Shift Pagi 08:00 - 16:00',
                'start_time' => '08:00:00',
                'end_time' => '16:00:00',
                'break_minutes' => 60,
                'is_night_shift' => false,
            ]
        );

        $employeesData = [];
        $now = now()->toDateTimeString();

        // 500 karyawan
        for ($i = 1; $i <= 500; $i++) {
            $num = str_pad((string) $i, 4, '0', STR_PAD_LEFT);
            $nik = "320101000000{$num}";
            $employeesData[] = [
                'id' => (string) Str::uuid(),
                'user_id' => null,
                'employee_number' => "EMP-T1-{$num}",
                'name' => "Karyawan T1 #{$num}",
                'nik' => $nik,
                'nik_bidx' => BlindIndex::forNik($nik),
                'nik_hash' => hash('sha256', $nik),
                'email' => "emp{$num}@superweb.test",
                'department_id' => $dept->id,
                'position' => 'Operator Lapangan',
                'employment_type' => 'PKWTT',
                'basic_salary_idr' => 10_000_000,
                'allowances_idr' => 0,
                'bank_name' => 'Bank Mandiri',
                'bank_account_number' => "100-00-{$num}999",
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($employeesData, 100) as $chunk) {
            DB::table('hcm_employees')->upsert($chunk, ['employee_number']);
        }
    }
}
