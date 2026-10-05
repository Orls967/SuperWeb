<?php

declare(strict_types=1);

namespace Modules\Treasury\Console\Commands;

use Illuminate\Console\Command;
use Modules\Treasury\Application\Services\TreasuryService;

class AuditTreasuryCommand extends Command
{
    protected $signature = 'treasury:audit';

    protected $description = 'Audit multi-currency, bank reconciliation, credit facilities, and treasury balances';

    public function handle(TreasuryService $service): int
    {
        $this->info('Starting Treasury Audit...');

        $result = $service->auditTreasury();

        $this->line("Status: {$result['status']}");
        $this->line("Currencies: {$result['currencies_count']}");
        $this->line("Exchange Rates: {$result['rates_count']}");
        $this->line("Bank Accounts: {$result['bank_accounts_count']}");
        $this->line("Unreconciled Statements: {$result['unreconciled_statements']}");
        $this->line("Active Facilities: {$result['active_facilities']}");
        $this->line("Discrepancies: {$result['discrepancy_count']}");

        if ($result['discrepancy_count'] > 0) {
            $this->error('Treasury audit FAILED: Discrepancy detected in credit facilities / balances!');

            return self::FAILURE;
        }

        $this->info('Treasury audit PASSED with 0 discrepancy.');

        return self::SUCCESS;
    }
}
