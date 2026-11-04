<?php

declare(strict_types=1);

namespace Modules\Hcm\Console\Commands;

use Illuminate\Console\Command;
use Modules\Hcm\Application\Services\HcmPayrollCalculationService;
use Modules\Hcm\Domain\Models\Employee;

class RunPayrollCommand extends Command
{
    protected $signature = 'hcm:run-payroll {period : Periode penggajian YYYY-MM}';

    protected $description = 'Menjalankan kalkulasi penggajian bulanan enterprise secara idempoten';

    public function handle(HcmPayrollCalculationService $service): int
    {
        $period = (string) $this->argument('period');
        $this->info("Menjalankan payroll HCM untuk periode {$period}...");

        $employees = Employee::where('is_active', true)->get();
        $count = 0;

        foreach ($employees as $employee) {
            $service->calculatePayroll($employee, $period, "hcm:pay:run:{$period}:{$employee->id}");
            $count++;
        }

        $this->info("Payroll selesai diproses untuk {$count} karyawan.");

        return self::SUCCESS;
    }
}
