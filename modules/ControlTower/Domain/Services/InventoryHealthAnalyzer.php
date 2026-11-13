<?php

declare(strict_types=1);

namespace Modules\ControlTower\Domain\Services;

use Carbon\CarbonImmutable;
use Modules\ControlTower\Contracts\ItemAvailabilityReader;
use Modules\ControlTower\Domain\Models\EchelonStock;

/**
 * Analisis kesehatan persediaan multi-echelon, aging bucket, dan eksposur modal kerja tertahan (X53-3).
 *
 * Seluruh kalkulasi nilai rupiah (IDR) menggunakan integer (BigInt-safe, tanpa float).
 */
final class InventoryHealthAnalyzer
{
    public const STATUS_DEAD_STOCK = 'DEAD_STOCK';

    public const STATUS_SLOW_MOVING = 'SLOW_MOVING';

    public const STATUS_EXCESS = 'EXCESS';

    public const STATUS_SHORTAGE_RISK = 'SHORTAGE_RISK';

    public const STATUS_HEALTHY = 'HEALTHY';

    public const BUCKET_0_30 = '0-30';

    public const BUCKET_31_90 = '31-90';

    public const BUCKET_91_180 = '91-180';

    public const BUCKET_OVER_180 = '>180';

    public function __construct(
        private readonly ItemAvailabilityReader $availabilityReader,
    ) {}

    /**
     * Analisis kesehatan persediaan untuk satu entri EchelonStock.
     *
     * @return array{
     *   item_code: string,
     *   echelon_node: string,
     *   health_status: string,
     *   aging_bucket: string,
     *   on_hand_qty: int,
     *   quarantine_qty: int,
     *   available_qty: int,
     *   daily_demand_qty: int,
     *   dos_days: int,
     *   oldest_lot_age_days: int,
     *   tied_capital_idr: int,
     *   obsolescence_risk_idr: int,
     *   snapshot_date: string
     * }
     */
    public function analyze(
        EchelonStock $stock,
        int $dailyDemandQty,
        int $unitCostIdr = 0,
        string $asOfDate = '',
    ): array {
        $effectiveDate = $asOfDate !== '' ? $asOfDate : CarbonImmutable::now()->toDateString();
        $position = $this->availabilityReader->positionFor((string) $stock->item_code, $effectiveDate);

        $dailyDemandQty = max(0, $dailyDemandQty);
        $unitCostIdr = max(0, $unitCostIdr);
        $safetyStockQty = max(0, (int) $stock->safety_stock_qty);
        $oldestLotAgeDays = max(0, (int) ($position['oldest_lot_age_days'] ?? 0));
        $quarantineQty = max(0, (int) ($position['quarantine'] ?? 0));

        $readerOnHand = max(0, (int) ($position['on_hand'] ?? 0));
        $readerAvailable = max(0, (int) ($position['available'] ?? 0));
        $readerHold = max(0, (int) ($position['hold'] ?? 0));

        $onHandQty = max(0, (int) $stock->on_hand_qty);
        if ($onHandQty === 0 && $readerOnHand > 0) {
            $onHandQty = $readerOnHand;
        }

        $echelonAvailable = max(0, $onHandQty - max(0, (int) $stock->reserved_qty));
        $hasWmsBreakdown = $readerOnHand > 0 || $quarantineQty > 0 || $readerHold > 0;
        $availableQty = $hasWmsBreakdown
            ? min($echelonAvailable, $readerAvailable)
            : $echelonAvailable;

        $dosDays = $this->computeDosDays($onHandQty, $dailyDemandQty);
        $agingBucket = $this->classifyAgingBucket($oldestLotAgeDays);
        $healthStatus = $this->classifyStatus(
            onHandQty: $onHandQty,
            availableQty: $availableQty,
            safetyStockQty: $safetyStockQty,
            dailyDemandQty: $dailyDemandQty,
            dosDays: $dosDays,
            oldestLotAgeDays: $oldestLotAgeDays,
        );

        $tiedCapitalIdr = $onHandQty * $unitCostIdr;
        $obsolescenceRiskIdr = $this->computeObsolescenceRiskIdr(
            healthStatus: $healthStatus,
            onHandQty: $onHandQty,
            quarantineQty: $quarantineQty,
            safetyStockQty: $safetyStockQty,
            unitCostIdr: $unitCostIdr,
            tiedCapitalIdr: $tiedCapitalIdr,
        );

        return [
            'item_code' => (string) $stock->item_code,
            'echelon_node' => (string) $stock->echelon_node,
            'health_status' => $healthStatus,
            'aging_bucket' => $agingBucket,
            'on_hand_qty' => $onHandQty,
            'quarantine_qty' => $quarantineQty,
            'available_qty' => $availableQty,
            'daily_demand_qty' => $dailyDemandQty,
            'dos_days' => $dosDays,
            'oldest_lot_age_days' => $oldestLotAgeDays,
            'tied_capital_idr' => $tiedCapitalIdr,
            'obsolescence_risk_idr' => $obsolescenceRiskIdr,
            'snapshot_date' => $effectiveDate,
        ];
    }

    public function classifyAgingBucket(int $oldestLotAgeDays): string
    {
        return match (true) {
            $oldestLotAgeDays <= 30 => self::BUCKET_0_30,
            $oldestLotAgeDays <= 90 => self::BUCKET_31_90,
            $oldestLotAgeDays <= 180 => self::BUCKET_91_180,
            default => self::BUCKET_OVER_180,
        };
    }

    public function classifyStatus(
        int $onHandQty,
        int $availableQty,
        int $safetyStockQty,
        int $dailyDemandQty,
        int $dosDays,
        int $oldestLotAgeDays,
    ): string {
        if ($onHandQty > 0 && ($oldestLotAgeDays > 180 || $dailyDemandQty <= 0)) {
            return self::STATUS_DEAD_STOCK;
        }

        if ($onHandQty > 0 && ($dosDays > 90 || ($oldestLotAgeDays >= 91 && $oldestLotAgeDays <= 180))) {
            return self::STATUS_SLOW_MOVING;
        }

        if ($safetyStockQty > 0 && $availableQty < $safetyStockQty) {
            return self::STATUS_SHORTAGE_RISK;
        }

        if ($safetyStockQty > 0 && $onHandQty > ($safetyStockQty * 3)) {
            return self::STATUS_EXCESS;
        }

        return self::STATUS_HEALTHY;
    }

    private function computeDosDays(int $onHandQty, int $dailyDemandQty): int
    {
        if ($onHandQty <= 0) {
            return 0;
        }

        if ($dailyDemandQty <= 0) {
            return 9999;
        }

        return intdiv($onHandQty, $dailyDemandQty);
    }

    private function computeObsolescenceRiskIdr(
        string $healthStatus,
        int $onHandQty,
        int $quarantineQty,
        int $safetyStockQty,
        int $unitCostIdr,
        int $tiedCapitalIdr,
    ): int {
        if ($tiedCapitalIdr <= 0 || $unitCostIdr <= 0) {
            return 0;
        }

        return match ($healthStatus) {
            self::STATUS_DEAD_STOCK => $tiedCapitalIdr,
            self::STATUS_SLOW_MOVING => max(
                intdiv($tiedCapitalIdr, 2),
                min($tiedCapitalIdr, $quarantineQty * $unitCostIdr),
            ),
            self::STATUS_EXCESS => min(
                $tiedCapitalIdr,
                (max(0, $onHandQty - ($safetyStockQty * 3)) + $quarantineQty) * $unitCostIdr,
            ),
            default => min($tiedCapitalIdr, $quarantineQty * $unitCostIdr),
        };
    }
}
