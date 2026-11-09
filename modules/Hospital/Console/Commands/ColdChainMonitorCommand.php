<?php

declare(strict_types=1);

namespace Modules\Hospital\Console\Commands;

use Illuminate\Console\Command;
use Modules\Hospital\Domain\Models\ColdChainTelemetry;
use Modules\Hospital\Domain\Models\MedFridgeBreach;
use Modules\Hospital\Domain\Services\VaccineThermalStabilityCalculator;

class ColdChainMonitorCommand extends Command
{
    protected $signature = 'hsp:cold-chain-monitor {--fridge= : Filter kode unit lemari pendingin tertentu} {--days=1 : Jumlah hari riwayat pemantauan}';

    protected $description = 'Pemantauan real-time dan evaluasi Mean Kinetic Temperature (MKT) unit pendingin farmasi';

    public function handle(VaccineThermalStabilityCalculator $mktCalculator): int
    {
        $this->info('Memulai Analisis Stabilitas Termal Rantai Dingin Farmasi (Cold-Chain)...');

        $fridgeFilter = $this->option('fridge');
        $days = max(1, (int) $this->option('days'));

        $query = ColdChainTelemetry::where('recorded_at', '>=', now()->subDays($days));
        if ($fridgeFilter) {
            $query->where('fridge_unit_code', $fridgeFilter);
        }

        $units = $query->select('fridge_unit_code')->distinct()->pluck('fridge_unit_code')->all();

        if (empty($units)) {
            $this->warn('Tidak ada data telemetri cold-chain ditemukan pada periode yang dipilih.');

            return self::SUCCESS;
        }

        $rows = [];
        $unhealthyCount = 0;

        foreach ($units as $unitCode) {
            $temps = ColdChainTelemetry::where('fridge_unit_code', $unitCode)
                ->where('recorded_at', '>=', now()->subDays($days))
                ->orderBy('recorded_at')
                ->pluck('temperature_c')
                ->map(fn ($t) => (float) $t)
                ->all();

            if (empty($temps)) {
                continue;
            }

            $mkt = $mktCalculator->calculateMeanKineticTemperature($temps);
            if ($mkt['is_shelf_life_impacted']) {
                $unhealthyCount++;
            }

            $rows[] = [
                $unitCode,
                $mkt['sample_count'],
                "{$mkt['arithmetic_mean_c']} °C",
                "{$mkt['mkt_c']} °C",
                "{$mkt['min_temp_c']} / {$mkt['max_temp_c']} °C",
                $mkt['is_shelf_life_impacted'] ? '<error>WARNING / EXCURSION</error>' : '<info>OPTIMAL</info>',
                $mkt['recommended_action'],
            ];
        }

        $this->table(
            ['Unit Kulkas', 'Titik Sampel', 'Rata-rata Suhu', 'MKT', 'Min / Max Suhu', 'Status', 'Tindakan'],
            $rows
        );

        $activeBreaches = MedFridgeBreach::where('status', 'QUARANTINED')->count();
        $this->line("Total Unit Terpantau: " . count($rows) . " | Unit Beresiko: {$unhealthyCount} | Insiden Breach Aktif: {$activeBreaches}");

        return self::SUCCESS;
    }
}
