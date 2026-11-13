<?php

declare(strict_types=1);

namespace Modules\ControlTower\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\ControlTower\Domain\Algorithms\AtpCtpCalculator;
use Modules\ControlTower\Domain\Models\EchelonStock;
use Modules\ControlTower\Domain\Models\OrderPromise;
use Modules\Core\Contracts\ClockInterface;

final class EvaluateOrderPromiseAction
{
    public function __construct(
        private readonly AtpCtpCalculator $calculator,
        private readonly ClockInterface $clock,
    ) {}

    public function execute(
        string $orderRef,
        string $itemCode,
        int $requestedQty,
        int $bomComponentAvailableQty = 0,
        int $plantFreeCapacityUnits = 0,
        int $leadTimeDays = 3,
    ): OrderPromise {
        return DB::transaction(function () use (
            $orderRef,
            $itemCode,
            $requestedQty,
            $bomComponentAvailableQty,
            $plantFreeCapacityUnits,
            $leadTimeDays
        ): OrderPromise {
            $stocks = EchelonStock::query()->where('item_code', $itemCode)->get();
            $onHand = (int) $stocks->sum('on_hand_qty');
            $inTransit = (int) $stocks->sum('in_transit_qty');
            $committed = (int) $stocks->sum('reserved_qty');

            $eval = $this->calculator->evaluate(
                itemCode: $itemCode,
                onHandQty: $onHand,
                scheduledReceiptsQty: $inTransit,
                alreadyCommittedQty: $committed,
                requestedQty: $requestedQty,
                bomComponentAvailableQty: $bomComponentAvailableQty,
                plantFreeCapacityUnits: $plantFreeCapacityUnits,
            );

            $code = 'PRM-'.strtoupper(substr(hash('sha256', $orderRef.'|'.$itemCode.'|'.(string) OrderPromise::count()), 0, 10));

            return OrderPromise::create([
                'promise_code' => $code,
                'order_reference' => $orderRef,
                'item_code' => $itemCode,
                'requested_qty' => $requestedQty,
                'atp_confirmed_qty' => $eval['atp_confirmed_qty'],
                'ctp_manufacturing_qty' => $eval['ctp_manufacturing_qty'],
                'promised_delivery_date' => $this->clock->now()->addDays(max(1, $leadTimeDays))->toDateString(),
                'promise_status' => $eval['promise_status'],
            ]);
        });
    }
}
