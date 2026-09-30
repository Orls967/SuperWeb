<?php

declare(strict_types=1);

namespace Modules\Mall\Console\Commands;

use Illuminate\Console\Command;
use Modules\Mall\Application\Actions\GenerateMonthlyInvoicesAction;

class GenerateInvoicesCommand extends Command
{
    protected $signature = 'mall:generate-invoices 
                            {--month= : Periode bulan penagihan (format: YYYY-MM, default: bulan berjalan)}
                            {--property= : Filter berdasarkan ID properti mall}';

    protected $description = 'Generate tagihan sewa & utilitas bulanan seluruh tenant mall secara idempoten';

    public function handle(GenerateMonthlyInvoicesAction $action): int
    {
        $month = $this->option('month') ?: date('Y-m');
        $propertyId = $this->option('property') ? (int) $this->option('property') : null;

        $this->info("Menjalankan generate invoice mall untuk periode [{$month}]...");

        $invoices = $action->generateAll($month, $propertyId);

        if (empty($invoices)) {
            $this->warn('Tidak ada lease aktif yang perlu dibuatkan invoice untuk periode ini.');

            return self::SUCCESS;
        }

        $tableData = [];
        $totalBilled = 0;

        foreach ($invoices as $inv) {
            $totalBilled += $inv->total_amount;
            $tableData[] = [
                'Invoice #' => $inv->invoice_number,
                'Tenant' => $inv->tenant?->brand_name,
                'Unit' => $inv->lease?->unit?->unit_number,
                'Subtotal' => 'Rp '.number_format($inv->subtotal, 0, ',', '.'),
                'Total' => 'Rp '.number_format($inv->total_amount, 0, ',', '.'),
                'Status' => $inv->status->label(),
                'Jatuh Tempo' => $inv->due_date->format('d/m/Y'),
            ];
        }

        $this->table(['Invoice #', 'Tenant', 'Unit', 'Subtotal', 'Total', 'Status', 'Jatuh Tempo'], $tableData);
        $this->info('Total Invoices: '.count($invoices).' | Total Ditagih: Rp '.number_format($totalBilled, 0, ',', '.'));

        return self::SUCCESS;
    }
}
