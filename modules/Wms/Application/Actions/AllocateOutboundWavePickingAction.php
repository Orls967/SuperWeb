<?php

declare(strict_types=1);

namespace Modules\Wms\Application\Actions;

use InvalidArgumentException;
use Modules\Shared\Application\BaseAction;
use Modules\Wms\Application\Services\WmsService;
use Modules\Wms\Domain\Models\BinStock;
use Modules\Wms\Domain\Models\Task;
use Modules\Wms\Domain\Models\Wave;

final class AllocateOutboundWavePickingAction extends BaseAction
{
    public function __construct(
        private readonly WmsService $wmsService
    ) {}

    /**
     * Allocates outbound inventory across bins and releases a picking wave.
     *
     * @param  list<array{product_id: int, qty: int}>  $requirements
     * @return array{wave: Wave, tasks: list<Task>}
     */
    public function execute(string $strategy, array $requirements): array
    {
        if (empty($requirements)) {
            throw new InvalidArgumentException('Daftar kebutuhan wave picking tidak boleh kosong.');
        }

        return $this->transaction(function () use ($strategy, $requirements) {
            $wave = $this->wmsService->createWave($strategy);
            $allocatedTasks = [];

            foreach ($requirements as $req) {
                $productId = $req['product_id'];
                $neededQty = $req['qty'];

                if ($neededQty <= 0) {
                    continue;
                }

                // Query available stocks in bins
                $availableStocks = BinStock::query()
                    ->where('product_id', $productId)
                    ->where('status', 'available')
                    ->where('qty', '>', 0)
                    ->with('bin.rack.zone')
                    ->get();

                $remaining = $neededQty;

                foreach ($availableStocks as $stock) {
                    if ($remaining <= 0) {
                        break;
                    }

                    $take = min((int) $stock->qty, $remaining);
                    $task = Task::create([
                        'wave_id' => $wave->id,
                        'warehouse_id' => $stock->bin->rack->zone->warehouse_id ?? 1,
                        'kind' => 'pick',
                        'status' => 'pending',
                        'product_id' => $productId,
                        'qty' => $take,
                        'bin_id' => $stock->bin_id,
                    ]);

                    $allocatedTasks[] = $task;
                    $remaining -= $take;
                }

                if ($remaining > 0) {
                    throw new InvalidArgumentException("Stok tidak mencukupi untuk produk ID {$productId}. Kurang {$remaining} unit.");
                }
            }

            // Release the wave
            $wave = $this->wmsService->releaseWave($wave);

            $this->audit('wms.wave_picking_allocated', $wave, null, [
                'strategy' => $strategy,
                'task_count' => count($allocatedTasks),
            ]);

            return [
                'wave' => $wave,
                'tasks' => $allocatedTasks,
            ];
        });
    }
}
