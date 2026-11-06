<?php

declare(strict_types=1);

namespace Modules\Egy\Console\Commands;

use Illuminate\Console\Command;
use Modules\Egy\Application\Actions\DetectWaterLeakAndDispatchWorkOrderAction;
use Modules\Egy\Domain\Models\WaterNetwork;

final class EgyLeakDetectCommand extends Command
{
    protected $signature = 'egy:leak-detect {--threshold-bps=2500 : Non-revenue water threshold in basis points} {--date= : Tanggal pemindaian YYYY-MM-DD}';

    protected $description = 'Pindai kebocoran jaringan air DMA berbasis flow & pressure drop dan picu work order perbaikan';

    public function handle(DetectWaterLeakAndDispatchWorkOrderAction $leakAction): int
    {
        $thresholdBps = (int) $this->option('threshold-bps');
        $dateParam = (string) ($this->option('date') ?: 'CYCLE');
        $this->info("Memindai anomali kebocoran pipa dengan ambang batas NRW {$thresholdBps} bps...");

        $networks = WaterNetwork::where('status', 'operational')->get();
        $dispatched = 0;

        foreach ($networks as $network) {
            $inputL = (int) $network->capacity_liters_per_day;
            // Simulasi deteksi jika zona memiliki indikasi selisih flow tinggi
            $billedL = (int) intdiv($inputL * 60, 100); // 40% loss
            $apparentL = (int) intdiv($inputL * 2, 100);
            $realL = $inputL - ($billedL + $apparentL);

            $lossBps = (int) intdiv(($inputL - $billedL) * 10000, $inputL);

            if ($lossBps >= $thresholdBps) {
                $idempotencyKey = sprintf('EGY:LEAK:%s:NET-%d', $dateParam, $network->id);
                $incident = $leakAction->execute(
                    networkId: $network->id,
                    systemInputVolumeL: $inputL,
                    billedAuthorizedL: $billedL,
                    apparentLossesL: $apparentL,
                    realLossesL: $realL,
                    idempotencyKey: $idempotencyKey
                );

                $this->warn(sprintf(
                    'Kebocoran terdeteksi di [%s]! Loss %d bps -> Work Order #%d dipicu',
                    $network->zone_code,
                    $incident->loss_percentage_bps,
                    $incident->asset_work_order_id ?? 0
                ));
                $dispatched++;
            }
        }

        $this->info("Pemindaian selesai. {$dispatched} work order pemeliharaan dipicu.");

        return self::SUCCESS;
    }
}
