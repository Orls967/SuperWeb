<?php

declare(strict_types=1);

namespace Modules\Forest\Console\Commands;

use Illuminate\Console\Command;
use Modules\Forest\Domain\Models\ForestConcessionPlot;
use Modules\Forest\Domain\Models\ForestHarvestPermit;
use Modules\Forest\Domain\Models\ForestTimberSale;

final class AuditForestCommand extends Command
{
    protected $signature = 'for:audit';

    protected $description = 'Audit integritas kuota tebang kehutanan, gapless hash-chain log kayu, dan pungutan PSDH DR';

    public function handle(): int
    {
        $this->info('Memulai audit integritas modul Kehutanan (Forest for_)...');
        $violations = 0;

        // 1. Audit Kuota Petak: harvested_volume_m3 <= annual_quota_m3
        $overQuotaPlots = ForestConcessionPlot::whereColumn('harvested_volume_m3', '>', 'annual_quota_m3')->get();
        foreach ($overQuotaPlots as $plot) {
            $this->error("Pelanggaran kuota petak {$plot->plot_code}: tebang {$plot->harvested_volume_m3} m3 > kuota {$plot->annual_quota_m3} m3.");
            $violations++;
        }

        // 2. Audit Izin Tebang: realisasi tebang kayu permit <= authorized_volume_m3
        $overPermits = ForestHarvestPermit::whereColumn('harvested_volume_m3', '>', 'authorized_volume_m3')->get();
        foreach ($overPermits as $permit) {
            $this->error("Pelanggaran kuota izin {$permit->permit_code}: realisasi {$permit->harvested_volume_m3} m3 > izin {$permit->authorized_volume_m3} m3.");
            $violations++;
        }

        // 3. Audit Hash Chain Batang Kayu
        $permits = ForestHarvestPermit::with(['logs' => fn ($q) => $q->orderBy('serial_number')])->get();
        foreach ($permits as $permit) {
            $expectedPrevHash = str_repeat('0', 64);
            $expectedSerial = 1;

            foreach ($permit->logs as $log) {
                if ($log->serial_number !== $expectedSerial) {
                    $this->error("Celah nomor seri pada izin {$permit->permit_code}: serial {$log->serial_number} diharapkan {$expectedSerial}.");
                    $violations++;
                }

                if ($log->prev_hash !== $expectedPrevHash) {
                    $this->error("Kerusakan rantai hash pada log {$log->log_code}: prev_hash tidak cocok.");
                    $violations++;
                }

                $calculatedData = "{$log->log_code}:{$permit->permit_code}:{$log->serial_number}:{$log->volume_dm3}:{$log->prev_hash}";
                $expectedCanonical = hash('sha256', $calculatedData);

                if ($log->canonical_hash !== $expectedCanonical) {
                    $this->error("Kerusakan hash kanonikal pada log {$log->log_code}.");
                    $violations++;
                }

                $expectedPrevHash = $log->canonical_hash;
                $expectedSerial++;
            }
        }

        // 4. Audit Pungutan Kehutanan
        $invalidSales = ForestTimberSale::whereColumn('forestry_levy_idr', '>', 'total_amount_idr')->get();
        foreach ($invalidSales as $sale) {
            $this->error("Pelanggaran pungutan kehutanan pada penjualan {$sale->sale_code}: pungutan {$sale->forestry_levy_idr} > total {$sale->total_amount_idr}.");
            $violations++;
        }

        if ($violations > 0) {
            $this->error("Audit Kehutanan GAGAL: ditemukan {$violations} inkonsistensi.");

            return 1;
        }

        $this->info('Audit Kehutanan BERSIH: seluruh kuota, hash-chain kayu, dan pungutan valid.');

        return 0;
    }
}
