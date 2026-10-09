<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Console\Commands;

use Illuminate\Console\Command;
use Modules\EnterpriseFinance\Application\Services\EnterpriseFinanceService;

class AuditEnterpriseFinanceCommand extends Command
{
    protected $signature = 'enterprise:audit';

    protected $description = 'Audit enterprise finance (budget encumbrances, tax summaries, and SoD compliance)';

    public function handle(EnterpriseFinanceService $service): int
    {
        $this->info('Starting Enterprise Finance & Governance Audit...');

        $res = $service->auditEnterpriseFinance();

        $this->line("Status: {$res['status']}");
        $this->line("Budgets: {$res['budget_count']}");
        $this->line("Tax Summaries: {$res['tax_count']}");
        $this->line("SoD Rules: {$res['sod_rule_count']}");
        $this->line("Compliance Deadlines: {$res['deadline_count']}");
        $this->line("Discrepancies: {$res['discrepancy_count']}");

        if ($res['discrepancy_count'] > 0) {
            $this->error('Enterprise finance audit FAILED: Invariant violation detected!');

            return self::FAILURE;
        }

        $this->info('Enterprise Finance audit PASSED with 0 discrepancy.');

        return self::SUCCESS;
    }
}
