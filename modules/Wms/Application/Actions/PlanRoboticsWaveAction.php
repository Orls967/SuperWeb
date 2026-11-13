<?php

declare(strict_types=1);

namespace Modules\Wms\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\ClockInterface;
use Modules\Core\Contracts\OutboxBusInterface;
use Modules\Logistics\Contracts\CarrierCutoffProvider;
use Modules\Wms\Domain\Algorithms\WaveBinPacker;
use Modules\Wms\Domain\Models\Warehouse;
use Modules\Wms\Models\RobotMission;
use Modules\Wms\Models\RobotWave;

/**
 * Action perencanaan wave FFD (J2) per (carrier, cutoff) dan penyusunan ulang saat misi fallback manual (D279-5, D97).
 */
final readonly class PlanRoboticsWaveAction
{
    public function __construct(
        private WaveBinPacker $binPacker,
        private CarrierCutoffProvider $cutoffProvider,
        private OutboxBusInterface $outboxBus,
        private ClockInterface $clock,
    ) {}

    /**
     * @param  list<array{id: string|int, size: int, carrier: string, cutoff: int}>  $orders
     * @return array{waves: list<RobotWave>, certificate_hash: string, carrier_cutoffs: list<array<string, mixed>>}
     */
    public function execute(string $warehouseId, int $waveCapacity, array $orders, string $batchCode): array
    {
        return DB::transaction(function () use ($warehouseId, $waveCapacity, $orders, $batchCode): array {
            /** @var Warehouse $warehouse */
            $warehouse = Warehouse::query()->findOrFail($warehouseId);
            $cutoffs = $this->cutoffProvider->getCutoffsForWarehouse((string) $warehouse->code);

            $packed = $this->binPacker->pack($waveCapacity, $orders);
            $certHash = $packed['certificate']->sha256;
            $savedWaves = [];

            foreach ($packed['waves'] as $waveData) {
                $waveCode = sprintf('%s-W%03d', $batchCode, $waveData['wave_index']);
                $wave = RobotWave::query()->updateOrCreate(
                    ['wave_code' => $waveCode],
                    [
                        'warehouse_id' => $warehouseId,
                        'carrier_code' => $waveData['carrier'],
                        'cutoff_min' => $waveData['cutoff'],
                        'capacity' => $waveData['capacity'],
                        'allocated_load' => $waveData['load'],
                        'is_oversize' => $waveData['status'] === WaveBinPacker::STATUS_OVERSIZE,
                        'status' => $waveData['status'] === WaveBinPacker::STATUS_OVERSIZE ? 'oversize' : 'released',
                        'order_items' => $waveData['items'],
                        'certificate_hash' => $certHash,
                    ],
                );
                $savedWaves[] = $wave;
            }

            $nowIso = $this->clock->now()->toIso8601String();
            $payload = [
                'event_id' => "wms-wave-{$batchCode}",
                'occurred_at' => $nowIso,
                'warehouse_id' => $warehouseId,
                'batch_code' => $batchCode,
                'wave_count' => count($savedWaves),
                'certificate_hash' => $certHash,
            ];

            DB::afterCommit(function () use ($batchCode, $payload): void {
                $this->outboxBus->record('robotics.wave.released.v1', $payload, "robotics-wave-{$batchCode}");
            });

            return [
                'waves' => $savedWaves,
                'certificate_hash' => $certHash,
                'carrier_cutoffs' => $cutoffs,
            ];
        });
    }

    /**
     * Susun ulang wave aktif saat misi gagal dan beralih ke fallback_manual (tanpa kehilangan atau menggandakan order).
     *
     * @param  list<array{id: string|int, size: int, carrier: string, cutoff: int}>  $remainingOrders
     * @return array{waves: list<RobotWave>, certificate_hash: string}
     */
    public function replanAfterFallback(
        string $warehouseId,
        RobotMission $failedMission,
        int $waveCapacity,
        array $remainingOrders,
    ): array {
        // Arsipkan wave sebelumnya milik batch agar tidak ada order ganda di wave aktif
        RobotWave::query()
            ->where('warehouse_id', $warehouseId)
            ->whereIn('status', ['planned', 'released'])
            ->update(['status' => 'superseded']);

        $batchCode = "REPLAN-{$failedMission->mission_code}";
        $res = $this->execute($warehouseId, $waveCapacity, $remainingOrders, $batchCode);

        return [
            'waves' => $res['waves'],
            'certificate_hash' => $res['certificate_hash'],
        ];
    }
}
