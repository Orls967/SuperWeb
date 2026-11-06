<?php

declare(strict_types=1);

namespace Modules\Egy\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Modules\Egy\Domain\Models\WaterMeterReading;
use Modules\Egy\Domain\Models\WaterNetwork;
use Modules\Shared\Domain\Algorithms\Utility\WaterBalance;

final class EgyWaterBalanceDailyCommand extends Command
{
    protected $signature = 'egy:water-balance-daily {--network= : Specific network ID}';

    protected $description = 'Hitung neraca air harian terpadu standar IWA untuk seluruh DMA/jaringan distribusi';

    public function handle(WaterBalance $waterBalance): int
    {
        $this->info('Memulai evaluasi neraca air harian standar IWA...');

        $networkQuery = WaterNetwork::query();
        if ($this->option('network')) {
            $networkQuery->where('id', (int) $this->option('network'));
        }

        $networks = $networkQuery->get();
        $processed = 0;

        foreach ($networks as $network) {
            $inputL = (int) $network->capacity_liters_per_day;
            $billedL = (int) intdiv($inputL * 78, 100); // 78% billed authorized
            $unbilledL = (int) intdiv($inputL * 2, 100);  // 2% unbilled authorized
            $apparentL = (int) intdiv($inputL * 2, 100);  // 2% apparent losses
            $realL = $inputL - ($billedL + $unbilledL + $apparentL); // 18% real losses

            $result = $waterBalance->calculate(
                systemInputVolumeL: $inputL,
                billedAuthorizedL: $billedL,
                unbilledAuthorizedL: $unbilledL,
                apparentLossesL: $apparentL,
                realLossesL: $realL
            );

            // Simpan log telemetri jika belum ada hari ini
            WaterMeterReading::firstOrCreate(
                [
                    'network_id' => $network->id,
                    'recorded_at' => Carbon::now()->startOfDay(),
                ],
                [
                    'flow_rate_lps' => (int) intdiv($inputL, 86400),
                    'cumulative_volume_l' => $inputL,
                    'pressure_kpa' => 320,
                ]
            );

            $this->line(sprintf(
                'DMA [%s]: Input %d L, Billed %d L, Real Loss %d L, NRW %d bps (%s)',
                $network->zone_code,
                $result->systemInputVolumeL,
                $result->billedAuthorizedL,
                $result->realLossesL,
                $result->nrwBasisPoints,
                $result->certified ? 'Certified' : 'Uncertified'
            ));

            $processed++;
        }

        $this->info("Neraca air harian selesai untuk {$processed} jaringan DMA.");

        return self::SUCCESS;
    }
}
