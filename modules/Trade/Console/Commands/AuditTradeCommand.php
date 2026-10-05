<?php

declare(strict_types=1);

namespace Modules\Trade\Console\Commands;

use Illuminate\Console\Command;
use Modules\Trade\Application\Services\TradeService;

class AuditTradeCommand extends Command
{
    protected $signature = 'trade:audit';

    protected $description = 'Audit cross-border trade operations, orders, duties, and hash-chain tracking';

    public function handle(TradeService $service): int
    {
        $this->info('Starting Cross-Border Trade Audit...');

        $res = $service->auditTrade();

        $this->line("Status: {$res['status']}");
        $this->line("Export Orders: {$res['export_orders_count']}");
        $this->line("Import Orders: {$res['import_orders_count']}");
        $this->line("Trade Disputes: {$res['disputes_count']}");
        $this->line("Broken Tracking Chains: {$res['broken_chains_count']}");

        if ($res['broken_chains_count'] > 0) {
            $this->error('Trade audit FAILED: Broken tracking chain detected!');

            return self::FAILURE;
        }

        $this->info('Trade audit PASSED with 0 discrepancy.');

        return self::SUCCESS;
    }
}
