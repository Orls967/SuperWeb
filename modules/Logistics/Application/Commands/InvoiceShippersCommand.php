<?php

declare(strict_types=1);

namespace Modules\Logistics\Application\Commands;

use Illuminate\Console\Command;
use Modules\Logistics\Application\Actions\GenerateMonthlyInvoicesAction;

class InvoiceShippersCommand extends Command
{
    protected $signature = 'lgx:invoice-shippers {period? : Periode penagihan (format: YYYY-MM)}';

    protected $description = 'Terbitkan tagihan bulanan pascabayar (postpaid) B2B secara idempoten per shipper';

    public function handle(GenerateMonthlyInvoicesAction $action): int
    {
        $period = (string) ($this->argument('period') ?: now()->format('Y-m'));

        $this->info("Menjalankan penagihan bulanan logistik B2B untuk periode {$period}...");

        $invoices = $action->execute($period);

        if ($invoices->isEmpty()) {
            $this->warn("Tidak ada tagihan baru yang perlu diterbitkan untuk periode {$period} (seluruh shipment sudah tertagih atau tidak ada aktivitas baru).");

            return self::SUCCESS;
        }

        $this->info("Berhasil menerbitkan {$invoices->count()} invoice logistik:");

        $rows = $invoices->map(fn ($inv) => [
            $inv->invoice_number,
            $inv->shipper?->name ?? "Shipper #{$inv->shipper_id}",
            $inv->billing_period,
            'Rp '.number_format($inv->total_amount_idr, 0, ',', '.'),
            $inv->due_date->format('Y-m-d'),
        ])->toArray();

        $this->table(
            ['No. Invoice', 'Nama Shipper', 'Periode', 'Total Tagihan', 'Jatuh Tempo'],
            $rows
        );

        return self::SUCCESS;
    }
}
