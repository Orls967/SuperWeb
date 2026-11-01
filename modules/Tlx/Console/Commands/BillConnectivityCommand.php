<?php

declare(strict_types=1);

namespace Modules\Tlx\Console\Commands;

use Illuminate\Console\Command;
use Modules\Tlx\Application\Services\ConnectivityBillingService;
use Modules\Tlx\Domain\Models\IotDevice;

class BillConnectivityCommand extends Command
{
    protected $signature = 'tlx:bill-connectivity {--period= : Periode penagihan format YYYY-MM}';

    protected $description = 'Penerbitan tagihan konektivitas IoT bulanan via Intercompany Lini 14';

    public function handle(ConnectivityBillingService $billingService): int
    {
        $period = (string) ($this->option('period') ?: now()->format('Y-m'));
        $this->info("Memulai billing konektivitas untuk periode {$period}...");

        $entities = IotDevice::distinct()->pluck('owner_entity_id');
        $processed = 0;

        foreach ($entities as $entityId) {
            $idempKey = "TLX-BILL:{$entityId}:{$period}";
            $invoice = $billingService->billEntityConnectivity([
                'owner_entity_id' => (string) $entityId,
                'billing_period' => $period,
                'idempotency_key' => $idempKey,
            ]);

            $this->line("Invoice {$invoice->invoice_number} untuk {$entityId}: Rp {$invoice->total_charge_minor}");
            $processed++;
        }

        $this->info("Selesai menerbitkan tagihan untuk {$processed} entitas.");

        return self::SUCCESS;
    }
}
