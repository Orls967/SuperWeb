<?php

declare(strict_types=1);

namespace Modules\Tlx\Console\Commands;

use Illuminate\Console\Command;
use Modules\Tlx\Application\Services\ColocationBillingService;
use Modules\Tlx\Domain\Models\ColocationContract;

class BillColocationCommand extends Command
{
    protected $signature = 'tlx:bill-colocation {--period= : Periode penagihan format YYYY-MM}';

    protected $description = 'Penerbitan tagihan colocation data center bulanan Lini 14';

    public function handle(ColocationBillingService $billingService): int
    {
        $period = (string) ($this->option('period') ?: now()->format('Y-m'));
        $this->info("Memulai billing colocation DC untuk periode {$period}...");

        $contracts = ColocationContract::where('port_status', 'UP')->get();
        $processed = 0;

        foreach ($contracts as $contract) {
            $idempKey = "TLX-COLO-BILL:{$contract->id}:{$period}";
            $invoice = $billingService->billColocationContract([
                'contract_id' => $contract->id,
                'billing_period' => $period,
                'measured_peak_kw' => $contract->power_commit_kw,
                'idempotency_key' => $idempKey,
            ]);

            $this->line("Invoice {$invoice->invoice_number} untuk {$contract->tenant_party_id}: Rp {$invoice->total_minor}");
            $processed++;
        }

        $this->info("Selesai menerbitkan tagihan colocation untuk {$processed} kontrak.");

        return self::SUCCESS;
    }
}
