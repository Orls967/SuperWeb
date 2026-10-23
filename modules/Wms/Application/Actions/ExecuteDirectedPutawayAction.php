<?php

declare(strict_types=1);

namespace Modules\Wms\Application\Actions;

use App\Models\User;
use InvalidArgumentException;
use Modules\Shared\Application\BaseAction;
use Modules\Wms\Application\Services\WmsService;
use Modules\Wms\Domain\Models\Bin;
use Modules\Wms\Domain\Models\BinStock;
use Modules\Wms\Domain\Models\Task;
use Modules\Wms\Domain\Models\Zone;

final class ExecuteDirectedPutawayAction extends BaseAction
{
    public function __construct(
        private readonly WmsService $wmsService
    ) {}

    /**
     * Executes directed putaway into target bin or best available bin in zone.
     *
     * @return array{stock: BinStock, task: Task}
     */
    public function execute(
        int $productId,
        int $qty,
        ?Bin $preferredBin = null,
        ?Zone $targetZone = null,
        ?User $operator = null,
        ?string $lotNumber = null,
        ?string $serialNumber = null,
        string $status = 'available'
    ): array {
        if ($qty <= 0) {
            throw new InvalidArgumentException('Kuantitas putaway harus lebih dari 0.');
        }

        $user = $operator ?? auth()->user() ?? User::first();
        if ($user === null) {
            throw new InvalidArgumentException('Operator putaway diperlukan.');
        }

        return $this->transaction(function () use ($productId, $qty, $preferredBin, $targetZone, $user, $lotNumber, $status) {
            $destinationBin = $preferredBin;

            if ($destinationBin !== null && $destinationBin->capacityReached($qty)) {
                $destinationBin = null; // Preferred bin is full, auto-direct to alternative
            }

            if ($destinationBin === null && $targetZone !== null) {
                // Find first active bin in zone with sufficient capacity
                $candidates = Bin::query()
                    ->whereHas('rack', fn ($q) => $q->where('zone_id', $targetZone->id))
                    ->where('is_active', true)
                    ->get();

                foreach ($candidates as $candidate) {
                    if (! $candidate->capacityReached($qty)) {
                        $destinationBin = $candidate;
                        break;
                    }
                }
            }

            if ($destinationBin === null) {
                if ($preferredBin !== null) {
                    throw new InvalidArgumentException("Bin {$preferredBin->code} melebihi kapasitas dan tidak ada alternatif.");
                }
                throw new InvalidArgumentException('Tidak ada bin yang memiliki kapasitas cukup untuk putaway.');
            }

            // Execute putaway via WmsService
            $putawayResult = $this->wmsService->putaway(
                $destinationBin,
                $productId,
                (float) $qty,
                $user,
                $lotNumber,
                $status
            );

            $stock = $putawayResult['stock'];
            $task = $putawayResult['task'] ?? Task::query()->where('product_id', $productId)
                ->where('bin_id', $destinationBin->id)
                ->latest()
                ->first();

            if ($task === null) {
                $task = Task::create([
                    'warehouse_id' => $destinationBin->rack->zone->warehouse_id,
                    'kind' => 'putaway',
                    'status' => 'completed',
                    'product_id' => $productId,
                    'qty' => $qty,
                    'bin_id' => $destinationBin->id,
                    'target_bin_id' => $destinationBin->id,
                    'closed_at' => now(),
                ]);
            }

            $this->audit('wms.directed_putaway', $stock, null, [
                'bin_id' => $destinationBin->id,
                'qty' => $qty,
                'product_id' => $productId,
            ]);

            return [
                'stock' => $stock,
                'task' => $task,
            ];
        });
    }
}
