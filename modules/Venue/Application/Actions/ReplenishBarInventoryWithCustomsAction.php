<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Actions;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Shared\Application\BaseAction;
use Modules\Trade\Contracts\ImportLandedCostQuoter;
use Modules\Venue\Domain\Models\BarInventoryItem;

final class ReplenishBarInventoryWithCustomsAction extends BaseAction
{
    public function __construct(
        private readonly ImportLandedCostQuoter $customsQuoter
    ) {}

    public function execute(
        int $inventoryItemId,
        int $bottlesReceived,
        int $cifPerBottleIdr,
        string $alcoholTier = 'C'
    ): array {
        return DB::transaction(function () use ($inventoryItemId, $bottlesReceived, $cifPerBottleIdr, $alcoholTier) {
            if ($bottlesReceived <= 0) {
                throw new InvalidArgumentException('Jumlah botol diterima harus lebih besar dari 0.');
            }

            $item = BarInventoryItem::query()->lockForUpdate()->findOrFail($inventoryItemId);

            $totalCifIdr = $cifPerBottleIdr * $bottlesReceived;
            $unitVolumeMl = max(1, $item->unit_volume_ml);
            $totalVolumeLiters = (int) ceil(($unitVolumeMl * $bottlesReceived) / 1000.0);

            // Hitung Landed Cost & Cukai MMEA via Kontrak Aditif G7-K19
            $customsQuote = $this->customsQuoter->quoteMmeaLandedCost(
                cifIdr: $totalCifIdr,
                volumeLiters: $totalVolumeLiters,
                alcoholTier: $alcoholTier
            );

            $landedCostPerBottle = (int) intdiv($customsQuote['total_landed_cost_idr'], $bottlesReceived);
            $exciseDutyPerBottle = (int) intdiv($customsQuote['excise_tax_idr'], $bottlesReceived);

            $item->increment('current_stock_bottles', $bottlesReceived);
            $item->update([
                'cif_per_bottle_idr' => $cifPerBottleIdr,
                'cost_per_bottle_idr' => $landedCostPerBottle,
                'excise_duty_per_bottle_idr' => $exciseDutyPerBottle,
                'alcohol_tier' => $alcoholTier,
            ]);

            return [
                'inventory_item' => $item,
                'bottles_received' => $bottlesReceived,
                'total_cif_idr' => $totalCifIdr,
                'customs_duty_idr' => $customsQuote['customs_duty_idr'],
                'excise_tax_idr' => $customsQuote['excise_tax_idr'],
                'total_landed_cost_idr' => $customsQuote['total_landed_cost_idr'],
                'landed_cost_per_bottle_idr' => $landedCostPerBottle,
                'new_stock_bottles' => $item->current_stock_bottles,
            ];
        });
    }
}
