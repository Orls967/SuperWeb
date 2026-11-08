<?php

declare(strict_types=1);

namespace Modules\Aviation\Console\Commands;

use Illuminate\Console\Command;
use Modules\Aviation\Domain\Models\AviationExternalFactor;

class SyncAviationExternalFactorsCommand extends Command
{
    protected $signature = 'avi:sync-factors {--fuel-price= : Harga avtur per liter dalam rupiah minor} {--vona-alert= : Kode status VONA (GREEN/YELLOW/ORANGE/RED)}';

    protected $description = 'Sinkronisasi faktor eksternal aviasi (harga avtur MOPS, peringatan debu vulkanik VONA, inflasi bandara)';

    public function handle(): int
    {
        $this->info('Memperbarui faktor sensitivitas eksternal operasi penerbangan...');

        $fuelPrice = $this->option('fuel-price');
        $vonaAlert = $this->option('vona-alert');

        // Harga avtur standar: Rp 14.500 / liter = 1.450.000 minor IDR
        $fuelMinor = $fuelPrice !== null ? (int) $fuelPrice : 1450000;
        $vonaCode = $vonaAlert ?? 'YELLOW';

        $factor = AviationExternalFactor::create([
            'factor_type' => 'JET_A1_PRICE_INDEX',
            'effective_date' => now()->toDateString(),
            'value_numeric' => $fuelMinor,
            'unit' => 'IDR_MINOR_PER_LITER',
            'confidence_score_bps' => 9850,
            'source' => 'PERTAMINA_AVIATION_MOPS_FEED',
            'metadata' => [
                'vona_alert_level' => $vonaCode,
                'exchange_rate_usd_idr' => 15850,
                'sync_timestamp' => now()->toIso8601String(),
            ],
        ]);

        $this->info("Faktor eksternal berhasil dicatat (ID: {$factor->id}): Jet A-1 Rp ".number_format($fuelMinor / 100, 2)." / Liter, VONA: {$vonaCode}.");

        return self::SUCCESS;
    }
}

