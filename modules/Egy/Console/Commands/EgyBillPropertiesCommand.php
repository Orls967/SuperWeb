<?php

declare(strict_types=1);

namespace Modules\Egy\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Modules\Egy\Application\Services\EnergyGridAndMeteringService;
use Modules\Egy\Domain\Models\SmartMeter;

class EgyBillPropertiesCommand extends Command
{
    protected $signature = 'egy:bill-properties {period? : Billing period YYYY-MM} {--period= : Billing period option}';

    protected $description = 'Generates consolidated monthly utility invoices for all consumer properties';

    public function handle(EnergyGridAndMeteringService $service): int
    {
        $period = $this->argument('period') ?: $this->option('period') ?: CarbonImmutable::now()->format('Y-m');

        $properties = SmartMeter::where('is_active', true)
            ->whereNotNull('property_id')
            ->distinct()
            ->pluck('property_id');

        if ($properties->isEmpty()) {
            $properties = SmartMeter::where('status', 'ACTIVE')
                ->whereNotNull('consumer_property_id')
                ->distinct()
                ->pluck('consumer_property_id');
        }

        $this->info("Generating consolidated invoices for period [{$period}] across {$properties->count()} properties...");

        foreach ($properties as $propertyId) {
            $idempotencyKey = "BILL-CMD-{$propertyId}-{$period}";
            $invoice = $service->generateBilling((string) $propertyId, (string) $period, $idempotencyKey);
            $this->info("Invoice [{$invoice->invoice_number}]: Property {$propertyId}, Total: Rp ".number_format($invoice->total_amount_idr));
        }

        $this->info('Consolidated billing completed.');

        return Command::SUCCESS;
    }
}
