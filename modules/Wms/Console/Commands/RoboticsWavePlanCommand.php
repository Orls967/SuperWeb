<?php

declare(strict_types=1);

namespace Modules\Wms\Console\Commands;

use Illuminate\Console\Command;
use Modules\Wms\Application\Actions\PlanRoboticsWaveAction;
use Modules\Wms\Domain\Models\Warehouse;

/**
 * Command terjadwal per cutoff carrier untuk menyusun wave FFD J2 (D279-5, D279-9).
 */
final class RoboticsWavePlanCommand extends Command
{
    protected $signature = 'robotics:wave-plan {--warehouse= : ID gudang} {--capacity=70 : Kapasitas wave} {--batch=AUTO : Kode batch}';

    protected $description = 'Susun wave picking robotika per (carrier, cutoff) dengan FFD J2';

    public function handle(PlanRoboticsWaveAction $action): int
    {
        $whId = $this->option('warehouse');
        $capacity = max(1, (int) $this->option('capacity'));
        $batch = (string) $this->option('batch');

        $warehouse = $whId !== null
            ? Warehouse::query()->find($whId)
            : Warehouse::query()->orderBy('code')->first();

        if ($warehouse === null) {
            $this->warn('Tidak ada gudang ditemukan untuk robotics:wave-plan.');

            return self::SUCCESS;
        }

        $orders = [
            ['id' => "{$batch}-ORD-1", 'size' => 40, 'carrier' => 'JNE', 'cutoff' => 1700],
            ['id' => "{$batch}-ORD-2", 'size' => 30, 'carrier' => 'JNE', 'cutoff' => 1700],
            ['id' => "{$batch}-ORD-3", 'size' => 25, 'carrier' => 'SICEPAT', 'cutoff' => 1800],
        ];

        $res = $action->execute((string) $warehouse->id, $capacity, $orders, "{$batch}-{$warehouse->code}");
        $this->info(sprintf('robotics:wave-plan selesai (waves=%d, cert=%s).', count($res['waves']), substr($res['certificate_hash'], 0, 12)));

        return self::SUCCESS;
    }
}
