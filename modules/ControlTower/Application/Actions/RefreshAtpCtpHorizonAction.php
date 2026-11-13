<?php

declare(strict_types=1);

namespace Modules\ControlTower\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Schema;
use Modules\ControlTower\Contracts\ItemAvailabilityReader;
use Modules\ControlTower\Domain\Algorithms\AtpCtpNetter;
use Modules\ControlTower\Domain\Models\EchelonStock;
use Modules\ControlTower\Domain\Models\OrderPromise;
use Modules\Core\Contracts\ClockInterface;
use Modules\Distribution\Contracts\OpenDemandProvider;
use Modules\Manufacturing\Contracts\CapacityAndMaterialProvider;
use Modules\Store\Contracts\Product;

/**
 * Menyegarkan perhitungan horizon ATP/CTP multi-periode untuk satu atau seluruh item aktif.
 */
final class RefreshAtpCtpHorizonAction
{
    public function __construct(
        private readonly ItemAvailabilityReader $availabilityReader,
        private readonly CapacityAndMaterialProvider $capacityMaterialProvider,
        private readonly OpenDemandProvider $openDemandProvider,
        private readonly AtpCtpNetter $netter,
        private readonly ClockInterface $clock,
    ) {}

    /**
     * @return array{
     *     as_of_date: string,
     *     periods: int,
     *     items_refreshed: int,
     *     items: list<array{
     *         item_code: string,
     *         available: int,
     *         atp_horizon_end: int,
     *         ctp_horizon_end: int,
     *         atp_series: list<int>,
     *         ctp_series: list<int>,
     *         certificate_hash: string
     *     }>
     * }
     */
    public function execute(?string $itemCode = null, ?string $asOfDate = null, int $periods = 12): array
    {
        $effectiveAsOfDate = ($asOfDate !== null && $asOfDate !== '')
            ? CarbonImmutable::parse($asOfDate)->toDateString()
            : $this->clock->now()->toDateString();

        $itemCodes = $this->resolveItemCodes($itemCode);
        $items = [];

        foreach ($itemCodes as $code) {
            $position = $this->availabilityReader->positionFor($code, $effectiveAsOfDate);
            $capMat = $this->capacityMaterialProvider->cumulativeByPeriod($code, $periods, $effectiveAsOfDate);
            $mpsReceipts = $this->capacityMaterialProvider->mpsReceipts($code, $periods, $effectiveAsOfDate);
            $commitments = $this->openDemandProvider->commitmentsByPeriod($code, $periods, $effectiveAsOfDate);

            $net = $this->netter->net(
                itemCode: $code,
                onHand: $position['on_hand'],
                quarantine: $position['quarantine'],
                hold: $position['hold'],
                mpsReceipts: $mpsReceipts,
                commitments: $commitments,
                capacityCumulative: $capMat['capacity_cumulative'],
                materialCumulative: $capMat['material_cumulative'],
                requestedQty: 1,
                requestedPeriod: 1,
            );

            $items[] = [
                'item_code' => $code,
                'available' => $position['available'],
                'atp_horizon_end' => $net['atp_series'] !== [] ? $net['atp_series'][count($net['atp_series']) - 1] : 0,
                'ctp_horizon_end' => $net['ctp_series'] !== [] ? $net['ctp_series'][count($net['ctp_series']) - 1] : 0,
                'atp_series' => $net['atp_series'],
                'ctp_series' => $net['ctp_series'],
                'certificate_hash' => $net['certificate_hash'],
            ];
        }

        return [
            'as_of_date' => $effectiveAsOfDate,
            'periods' => $periods,
            'items_refreshed' => count($items),
            'items' => $items,
        ];
    }

    /**
     * @return list<string>
     */
    private function resolveItemCodes(?string $itemCode): array
    {
        if ($itemCode !== null && trim($itemCode) !== '') {
            return [trim($itemCode)];
        }

        $codes = EchelonStock::query()->distinct()->pluck('item_code')->all();
        $promiseCodes = OrderPromise::query()->distinct()->pluck('item_code')->all();
        $productSkus = Schema::hasTable('store_products')
            ? Product::query()->whereNotNull('sku')->distinct()->pluck('sku')->all()
            : [];

        $merged = array_values(array_unique(array_filter(
            array_merge($codes, $promiseCodes, $productSkus),
            static fn ($v): bool => is_string($v) && $v !== '',
        )));
        sort($merged);

        return $merged;
    }
}
