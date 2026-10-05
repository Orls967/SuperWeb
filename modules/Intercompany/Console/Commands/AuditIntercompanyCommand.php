<?php

declare(strict_types=1);

namespace Modules\Intercompany\Console\Commands;

use Illuminate\Console\Command;
use Modules\Intercompany\Application\Services\IntercompanyService;

class AuditIntercompanyCommand extends Command
{
    protected $signature = 'group:audit';

    protected $description = 'Audit intercompany transactions, elimination entries, loans, and transfer pricing margins';

    public function handle(IntercompanyService $service): int
    {
        $this->info('Starting Group Consolidation & Intercompany Audit...');

        $res = $service->auditIntercompany();

        $this->line("Status: {$res['status']}");
        $this->line("Mirror Transactions: {$res['transaction_count']}");
        $this->line("Intercompany Loans: {$res['loan_count']}");
        $this->line("Transfer Pricing Rules: {$res['rule_count']}");
        $this->line("Elimination Entries: {$res['elimination_count']}");
        $this->line("Discrepancies: {$res['discrepancy_count']}");

        if ($res['discrepancy_count'] > 0) {
            $this->error('Intercompany audit FAILED: Invariant violation detected!');

            return self::FAILURE;
        }

        $this->info('Group Consolidation audit PASSED with 0 discrepancy.');

        return self::SUCCESS;
    }
}
