<?php

declare(strict_types=1);

namespace Modules\ControlTower\Application\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\ControlTower\Contracts\ItemAvailabilityReader;
use Modules\ControlTower\Domain\Algorithms\AtpCtpCalculator;
use Modules\ControlTower\Domain\Models\EchelonStock;
use Modules\ControlTower\Domain\Models\OrderPromise;
use Modules\ControlTower\Domain\Models\OrderRiskScore;
use Modules\ControlTower\Domain\Services\OrderRiskScorer;
use Modules\Core\Contracts\ClockInterface;
use Modules\Logistics\Contracts\InTransitShipmentProvider;
use Modules\Procurement\Contracts\SupplierLeadTimeStatistics;

final class OrderRiskAssessmentService
{
    public function __construct(
        private readonly OrderRiskScorer $scorer,
        private readonly AtpCtpCalculator $atpCtpCalculator,
        private readonly ClockInterface $clock,
        private readonly ?SupplierLeadTimeStatistics $leadTimeStats = null,
        private readonly ?ItemAvailabilityReader $availabilityReader = null,
        private readonly ?InTransitShipmentProvider $inTransitProvider = null,
    ) {}

    public function assessItem(
        string $itemCode,
        ?string $orderReference = null,
        int $requestedQty = 0,
    ): OrderRiskScore {
        $now = $this->clock->now();
        $asOfDate = $now->toDateString();

        $stocks = EchelonStock::query()->where('item_code', $itemCode)->get();
        $onHand = (int) $stocks->sum('on_hand_qty');
        $inTransit = (int) $stocks->sum('in_transit_qty');
        $reserved = (int) $stocks->sum('reserved_qty');
        $safetyStock = (int) $stocks->sum('safety_stock_qty');

        $position = $this->availabilityReader?->positionFor($itemCode, $asOfDate) ?? [
            'on_hand' => $onHand,
            'available' => max(0, $onHand - $reserved),
            'quarantine' => 0,
            'hold' => 0,
            'oldest_lot_age_days' => 0,
        ];

        $availableSupply = $stocks->isNotEmpty()
            ? max(0, $onHand + $inTransit - $reserved)
            : max(0, (int) ($position['available'] ?? 0));

        if ($requestedQty > 0) {
            $atpEval = $this->atpCtpCalculator->evaluate(
                itemCode: $itemCode,
                onHandQty: $stocks->isNotEmpty() ? $onHand : (int) ($position['on_hand'] ?? 0),
                scheduledReceiptsQty: $inTransit,
                alreadyCommittedQty: $reserved,
                requestedQty: $requestedQty,
            );
            $availableSupply = $atpEval['available_atp_qty'];
        }

        $leadTime = ['mean_days' => 0, 'sigma_days' => 0, 'samples' => 0, 'on_time_permil' => 1000];
        if ($this->leadTimeStats !== null) {
            $primarySupplier = $this->leadTimeStats->primarySupplierForItem($itemCode);
            if ($primarySupplier !== null) {
                $leadTime = $this->leadTimeStats->forSupplierAndItem($primarySupplier, $itemCode);
            }
        }

        $overdueShipments = $this->inTransitProvider?->getOverdueInTransitShipments(72) ?? [];
        $overdueCount = count($overdueShipments);
        $maxHoursInTransit = 0;
        foreach ($overdueShipments as $shipment) {
            $hours = (int) ($shipment['hours_in_transit'] ?? 0);
            if ($hours > $maxHoursInTransit) {
                $maxHoursInTransit = $hours;
            }
        }

        $scored = $this->scorer->score(
            itemCode: $itemCode,
            requestedQty: $requestedQty,
            availableSupplyQty: $availableSupply,
            safetyStockQty: $safetyStock,
            leadTimeSigmaDays: (int) ($leadTime['sigma_days'] ?? 0),
            onTimePermil: (int) ($leadTime['on_time_permil'] ?? 1000),
            leadTimeSamples: (int) ($leadTime['samples'] ?? 0),
            oldestLotAgeDays: (int) ($position['oldest_lot_age_days'] ?? 0),
            onHandQty: (int) ($position['on_hand'] ?? $onHand),
            quarantineQty: (int) ($position['quarantine'] ?? 0),
            holdQty: (int) ($position['hold'] ?? 0),
            overdueShipmentsCount: $overdueCount,
            maxHoursInTransit: $maxHoursInTransit,
        );

        return DB::transaction(function () use ($itemCode, $orderReference, $scored, $now): OrderRiskScore {
            $query = OrderRiskScore::query()->where('item_code', $itemCode);
            if ($orderReference === null) {
                $query->whereNull('order_reference');
            } else {
                $query->where('order_reference', $orderReference);
            }

            $existing = $query->first();

            $attributes = [
                'item_code' => $itemCode,
                'order_reference' => $orderReference,
                'risk_score' => $scored['risk_score'],
                'risk_tier' => $scored['risk_tier'],
                'shortfall_component' => $scored['shortfall_component'],
                'lead_time_component' => $scored['lead_time_component'],
                'shelf_life_component' => $scored['shelf_life_component'],
                'transit_component' => $scored['transit_component'],
                'factors_json' => $scored['factors_json'],
                'mitigation_recommendation' => $scored['mitigation_plan'],
                'scored_at' => $now,
            ];

            if ($existing !== null) {
                $existing->update($attributes);

                return $existing->fresh() ?? $existing;
            }

            return OrderRiskScore::create($attributes);
        });
    }

    /**
     * @return Collection<int, OrderRiskScore>
     */
    public function scanAll(?string $itemCode = null): Collection
    {
        $stockItems = EchelonStock::query()
            ->when($itemCode !== null && $itemCode !== '', fn ($q) => $q->where('item_code', $itemCode))
            ->distinct()
            ->pluck('item_code');

        $promiseItems = OrderPromise::query()
            ->when($itemCode !== null && $itemCode !== '', fn ($q) => $q->where('item_code', $itemCode))
            ->distinct()
            ->pluck('item_code');

        $itemCodes = $stockItems->merge($promiseItems)->unique()->sort()->values();

        if ($itemCode !== null && $itemCode !== '' && $itemCodes->isEmpty()) {
            $itemCodes = collect([$itemCode]);
        }

        $results = collect();

        foreach ($itemCodes as $code) {
            $promises = OrderPromise::query()
                ->where('item_code', $code)
                ->orderBy('id')
                ->get();

            if ($promises->isNotEmpty()) {
                foreach ($promises as $promise) {
                    $results->push($this->assessItem(
                        itemCode: (string) $code,
                        orderReference: (string) $promise->order_reference,
                        requestedQty: (int) $promise->requested_qty,
                    ));
                }
            } else {
                $results->push($this->assessItem(itemCode: (string) $code));
            }
        }

        return $results;
    }
}
