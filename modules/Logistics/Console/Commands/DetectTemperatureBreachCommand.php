<?php

declare(strict_types=1);

namespace Modules\Logistics\Console\Commands;

use Illuminate\Console\Command;
use Modules\Logistics\Application\Services\Autonomous\AutonomousLogisticsService;
use Modules\Logistics\Domain\Models\Shipment;
use Modules\Logistics\Domain\Models\TemperatureReading;

class DetectTemperatureBreachCommand extends Command
{
    protected $signature = 'lgx:detect-temperature-breach';

    protected $description = 'Deteksi terjadwal dan otomatis terhadap episode breach suhu rantai dingin (> 10 menit) untuk penahanan utang carrier';

    public function handle(AutonomousLogisticsService $service): int
    {
        $this->info('Memulai evaluasi deteksi episode pelanggaran suhu cold-chain...');

        // Ambil pembacaan telemetri dengan status excursion
        $excursions = TemperatureReading::where('excursion', true)
            ->with('shipment')
            ->orderBy('recorded_at', 'asc')
            ->get();

        $breachesDetected = 0;

        // Kelompokkan per shipment_id
        $grouped = $excursions->groupBy('shipment_id');

        foreach ($grouped as $shipmentId => $readings) {
            /** @var Shipment|null $shipment */
            $shipment = Shipment::find($shipmentId);
            if (! $shipment) {
                continue;
            }

            $firstReading = $readings->first();
            $lastReading = $readings->last();

            $durationMinutes = (int) max(0, $firstReading->recorded_at->diffInMinutes($lastReading->recorded_at));

            // Jika durasi excursion melebihi 10 menit
            if ($durationMinutes > 10) {
                $carrierId = (int) ($shipment->carrier_id ?? 1);
                $carrierPayable = (int) ($shipment->cost_amount_idr ?? 1_000_000);

                $hold = $service->recordTemperatureCheck(
                    shipmentCode: $shipment->shipment_code ?? "SHP-{$shipmentId}",
                    carrierId: $carrierId,
                    recordedTempC: $lastReading->temperatureCelsius(),
                    maxAllowedTempC: ($lastReading->max_c10 !== null) ? ($lastReading->max_c10 / 10) : 8.0,
                    durationMinutes: $durationMinutes,
                    carrierPayableIdr: $carrierPayable,
                    legId: (int) $shipmentId,
                    episodeId: "EP-SHP-{$shipmentId}"
                );

                if ($hold) {
                    $breachesDetected++;
                    $this->line(" [BREACH] Shipment {$shipment->shipment_code}: durasi {$durationMinutes} mnt -> Hold {$hold->breach_code} Rp ".number_format($carrierPayable, 0, ',', '.'));
                }
            }
        }

        $this->info("Deteksi cold-chain selesai: {$breachesDetected} episode breach diproses.");

        return Command::SUCCESS;
    }
}
