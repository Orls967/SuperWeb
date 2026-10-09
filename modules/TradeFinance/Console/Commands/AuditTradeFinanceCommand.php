<?php

declare(strict_types=1);

namespace Modules\TradeFinance\Console\Commands;

use Illuminate\Console\Command;
use Modules\TradeFinance\Application\Services\TradeFinanceService;

class AuditTradeFinanceCommand extends Command
{
    protected $signature = 'tf:audit';

    protected $description = 'Audit trade finance instruments (L/C exposure, bank guarantees, trade loans, and collections)';

    public function handle(TradeFinanceService $service): int
    {
        $this->info('Starting Trade Finance Audit...');

        $res = $service->auditTradeFinance();

        $this->line("Status: {$res['status']}");
        $this->line("Letters of Credit: {$res['lc_count']}");
        $this->line("Documentary Collections: {$res['collection_count']}");
        $this->line("Bank Guarantees: {$res['guarantee_count']}");
        $this->line("Trade Loans: {$res['loan_count']}");
        $this->line("Discrepancies: {$res['discrepancy_count']}");

        if ($res['discrepancy_count'] > 0) {
            $this->error('Trade Finance audit FAILED: Invariant violation detected!');

            return self::FAILURE;
        }

        $this->info('Trade Finance audit PASSED with 0 discrepancy.');

        return self::SUCCESS;
    }
}
