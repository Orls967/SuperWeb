<?php

declare(strict_types=1);

namespace Modules\Vending\Console\Commands;

use Illuminate\Console\Command;
use Modules\Vending\Application\Services\SmartVendingService;

class AuditSmartVendingCommand extends Command
{
    protected $signature = 'vnd:audit';

    protected $description = 'Perform two-source verification of smart vending unit stocks, transactions, restock tasks, and auto-POs';

    public function handle(SmartVendingService $service): int
    {
        $this->info('Starting Smart Vending Operations & Forecasting Audit...');

        $result = $service->auditVending();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Audit Status', $result['status']],
                ['Vending Units Checked', $result['unit_count']],
                ['Transactions Checked', $result['transaction_count']],
                ['Restock Tasks Checked', $result['restock_task_count']],
                ['Discrepancies', $result['discrepancies']],
            ]
        );

        if ($result['discrepancies'] > 0) {
            $this->error("Smart vending audit FAILED with {$result['discrepancies']} discrepancies found.");

            return 1;
        }

        $this->info('Smart vending audit PASSED cleanly.');

        return 0;
    }
}
