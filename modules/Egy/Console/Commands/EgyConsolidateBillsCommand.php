<?php

declare(strict_types=1);

namespace Modules\Egy\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Modules\Egy\Application\Actions\ConsolidateMultiUtilityBillsAction;
use Modules\Egy\Domain\Models\SmartMeter;

final class EgyConsolidateBillsCommand extends Command
{
    protected $signature = 'egy:consolidate-bills {period? : Billing period format YYYY-MM}';

    protected $description = 'Kompilasi tagihan multi-utilitas (listrik, air, termal pendingin) ke dalam invoice terpadu';

    public function handle(ConsolidateMultiUtilityBillsAction $action): int
    {
        $period = $this->argument('period') ?: Carbon::now()->format('Y-m');
        $this->info("Menjalankan konsolidasi tagihan multi-utilitas untuk periode {$period}...");

        $properties = SmartMeter::select('property_id')->distinct()->whereNotNull('property_id')->pluck('property_id');
        $count = 0;

        foreach ($properties as $propertyId) {
            $propId = (int) $propertyId;
            $customerPartyId = $propId + 100; // deterministik
            $idempotencyKey = "EGY:CONS:{$period}:PROP-{$propId}";

            $invoice = $action->execute(
                propertyId: $propId,
                periodMonth: $period,
                customerPartyId: $customerPartyId,
                waterVolumeM3: 27,
                coolingKwhTh: 500,
                idempotencyKey: $idempotencyKey
            );

            $this->line("Invoice [{$invoice->invoice_number}] Property #{$propId}: Rp ".number_format($invoice->total_amount_idr));
            $count++;
        }

        $this->info("Konsolidasi tagihan selesai untuk {$count} properti.");

        return self::SUCCESS;
    }
}
