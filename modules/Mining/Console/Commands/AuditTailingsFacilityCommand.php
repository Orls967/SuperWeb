<?php

declare(strict_types=1);

namespace Modules\Mining\Console\Commands;

use Illuminate\Console\Command;
use Modules\Mining\Application\Actions\AuditTailingsDamSafetyAction;
use Modules\Mining\Domain\Models\MiningSite;

class AuditTailingsFacilityCommand extends Command
{
    protected $signature = 'mining:check-tailings {--dam=TSF-MAIN-01 : Kode Bendungan Tailing}';

    protected $description = 'Audit kepatuhan integritas struktural dan geoteknik bendungan tailing tambang (TSF) berstandar GISTM';

    public function handle(AuditTailingsDamSafetyAction $action): int
    {
        $damCode = (string) $this->option('dam');
        $site = MiningSite::first();

        if (! $site) {
            $this->error('Tidak ditemukan site konsesi tambang aktif.');

            return 1;
        }

        $this->info("Menjalankan audit inspeksi bendungan tailing #{$damCode}...");

        $result = $action->execute([
            'site_id' => (string) $site->id,
            'dam_code' => $damCode,
            'crest_elevation_meters' => 145.0,
            'pond_water_elevation_meters' => 143.2, // Freeboard 1.8 m
            'phreatic_line_ratio' => 0.35,
            'toe_seepage_flow_liters_per_minute' => 14.5,
            'historic_baseline_seepage_lpm' => 14.0,
            'cyclic_shear_stress_ratio' => 0.18,
            'cyclic_resistance_ratio' => 0.28, // FoS 1.55
        ]);

        $this->line("Tinggi Jagaan (Freeboard) : {$result['freeboard_meters']} meter");
        $this->line("Status Keamanan Struktur  : {$result['safety_verdict']}");
        $this->line("Arahan Mitigasi           : {$result['directive']}");

        if (! $result['is_inspection_passed']) {
            $this->error('AUDIT TSF GAGAL: Terdeteksi ancaman stabilitas tanggul tailing!');

            return 1;
        }

        $this->info('Audit TSF Sukses: Bendungan tailing beroperasi stabil dan aman.');

        return 0;
    }
}
