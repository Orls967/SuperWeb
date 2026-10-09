<?php

declare(strict_types=1);

namespace Modules\Hcm\Console\Commands;

use Illuminate\Console\Command;
use Modules\Hcm\Application\Services\HcmService;

class AuditHcmCommand extends Command
{
    protected $signature = 'hcm:audit';

    protected $description = 'Audit Human Capital Management payroll calculations, deductions, and labor allocations (0 discrepancies)';

    public function handle(HcmService $service): int
    {
        $this->info('Starting Human Capital Management (HCM) Audit...');

        $res = $service->auditHcm();

        $this->line("Status: {$res['status']}");
        $this->line("Employees: {$res['employee_count']}");
        $this->line("Payrolls: {$res['payroll_count']}");
        $this->line("Discrepancies: {$res['discrepancies']}");

        if ($res['discrepancies'] > 0) {
            $this->error('HCM audit FAILED: Invariant violation detected!');

            return self::FAILURE;
        }

        $this->info('HCM audit PASSED with 0 discrepancy.');

        return self::SUCCESS;
    }
}
