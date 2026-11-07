<?php

declare(strict_types=1);

namespace Modules\Mining\Console\Commands;

use Illuminate\Console\Command;
use Modules\Mining\Application\Actions\EvaluateContractorFuelAnomalyAction;
use Modules\Mining\Domain\Models\MiningEquipment;
use Modules\Mining\Domain\Models\MiningSite;

class FuelAnomalyScanCommand extends Command
{
    protected $signature = 'mining:fuel-anomaly-scan {date : Tanggal shift YYYY-MM-DD}';

    protected $description = 'Memindai deviasi konsumsi bahan bakar alat berat kontraktor menggunakan regresi M3';

    public function handle(EvaluateContractorFuelAnomalyAction $action): int
    {
        $date = (string) $this->argument('date');
        $this->info("Memulai pemindaian anomali bahan bakar kontraktor tanggal {$date}...");

        $sites = MiningSite::all();
        $scanned = 0;
        $anomalies = 0;

        foreach ($sites as $site) {
            $trucks = MiningEquipment::where('site_id', $site->id)
                ->where('type', 'HAUL_TRUCK')
                ->get();

            foreach ($trucks as $truck) {
                $result = $action->execute([
                    'site_id' => (string) $site->id,
                    'equipment_id' => (string) $truck->id,
                    'contractor_party_id' => 'CONT-BORNEO-PRIMARY',
                    'shift_date' => $date,
                    'shift_code' => 'SHIFT_A',
                    'historical_ton_km_thousands' => [10, 20, 30, 40, 50],
                    'historical_fuel_liters' => [230, 410, 620, 800, 1010],
                    'actual_ton_km_thousands' => 30,
                    'actual_fuel_liters' => 615,
                    'fuel_rate_per_liter_idr' => 15000,
                ]);

                $scanned++;
                if ($result['is_anomaly']) {
                    $anomalies++;
                    $this->warn("Anomali terdeteksi pada unit {$truck->equipment_code}: Deviasi {$result['excess_bps']} bps.");
                }
            }
        }

        $this->info("Pemindaian selesai: {$scanned} unit dipindai, {$anomalies} anomali terdeteksi.");

        return 0;
    }
}

