<?php

declare(strict_types=1);

namespace Modules\Egy\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Modules\Egy\Application\Services\ConsolidatedUtilityBillingService;
use Modules\Egy\Domain\Models\SmartMeter;

class EgyBillPropertiesCommand extends Command
{
    protected $signature = 'egy:bill-properties {--period= : Billing period YYYY-MM}';

    protected $description = 'Generates consolidated monthly utility invoices for all consumer properties';

    public function handle(ConsolidatedUtilityBillingService $billing): int
    {
        $period = $this->option('period') ?: CarbonImmutable::now()->subMonth()->format('Y-m');

        $properties = SmartMeter::where('status', 'ACTIVE')
            ->distinct()
            ->pluck('consumer_property_id');

        $this->info("Generating consolidated invoices for period [{$period}] across {$properties->count()} properties...");

        foreach ($properties as $propertyId) {
            $invoice = $billing->generateMonthlyInvoice($propertyId, $period);
            $this->info("Invoice [{$invoice->invoice_number}]: Property {$propertyId}, Total Wh: {$invoice->total_wh}, Charge: Rp ".number_format($invoice->total_consolidated_minor));
        }

        $this->info('Consolidated billing completed.');

        return Command::SUCCESS;
    }
}
