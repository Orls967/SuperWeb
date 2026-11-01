<?php

declare(strict_types=1);

namespace Modules\Egy\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Modules\Egy\Application\Actions\IngestSmartGridMeterTelemetryAction;
use Modules\Egy\Domain\Models\SmartMeter;

class EgyIngestReadingsCommand extends Command
{
    protected $signature = 'egy:ingest-readings {--meter= : Specific meter serial number} {--wh= : Cumulative Wh} {--reset : Flag if meter was reset}';

    protected $description = 'Ingests cumulative smart meter telemetry readings and calculates interval consumption';

    public function handle(IngestSmartGridMeterTelemetryAction $action): int
    {
        $meterSerial = $this->option('meter');
        $wh = $this->option('wh');

        if ($meterSerial !== null && $wh !== null) {
            $reading = $action->execute([
                'meter_number' => (string) $meterSerial,
                'cumulative_wh' => (int) $wh,
                'reading_at' => CarbonImmutable::now()->toIso8601String(),
                'is_meter_reset' => (bool) $this->option('reset'),
            ]);

            $this->info("Ingested meter [{$meterSerial}]: delta={$reading->delta_wh} Wh, status={$reading->interval_status}");

            return Command::SUCCESS;
        }

        $meters = SmartMeter::where('status', 'ACTIVE')->get();
        $this->info("Ingesting telemetry for {$meters->count()} active meters...");

        foreach ($meters as $meter) {
            $latestReading = $meter->readings()->latest('reading_at')->first();
            $prevWh = $latestReading ? (int) $latestReading->cumulative_wh : 0;
            $newWh = $prevWh + 25000; // Default simulated 25 kWh interval

            $action->execute([
                'meter_number' => $meter->meter_serial_number,
                'cumulative_wh' => $newWh,
                'reading_at' => CarbonImmutable::now()->toIso8601String(),
            ]);
        }

        $this->info('Telemetry ingestion completed.');

        return Command::SUCCESS;
    }
}
