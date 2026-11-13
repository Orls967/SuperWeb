<?php

declare(strict_types=1);

namespace Modules\ControlTower\Domain\Algorithms;

use InvalidArgumentException;
use Modules\ControlTower\Domain\Certificates\OrderPromiseCertificate;

final class AtpCtpCalculator
{
    /**
     * @return array{
     *     item_code: string,
     *     available_atp_qty: int,
     *     available_ctp_qty: int,
     *     atp_confirmed_qty: int,
     *     ctp_manufacturing_qty: int,
     *     shortfall_qty: int,
     *     promise_status: string,
     *     certificate: OrderPromiseCertificate
     * }
     */
    public function evaluate(
        string $itemCode,
        int $onHandQty,
        int $scheduledReceiptsQty,
        int $alreadyCommittedQty,
        int $requestedQty,
        int $bomComponentAvailableQty = 0,
        int $plantFreeCapacityUnits = 0,
    ): array {
        if ($itemCode === '' || $requestedQty <= 0) {
            throw new InvalidArgumentException('INVALID_PROMISE_REQUEST: Item dan kuantitas permintaan harus positif.');
        }

        $availableAtpQty = max(0, $onHandQty + $scheduledReceiptsQty - $alreadyCommittedQty);
        $atpConfirmedQty = min($requestedQty, $availableAtpQty);

        $remainingQty = $requestedQty - $atpConfirmedQty;
        $availableCtpQty = max(0, min($bomComponentAvailableQty, $plantFreeCapacityUnits));
        $ctpManufacturingQty = min($remainingQty, $availableCtpQty);

        $totalPromised = $atpConfirmedQty + $ctpManufacturingQty;
        $shortfallQty = $requestedQty - $totalPromised;

        $promiseStatus = match (true) {
            $totalPromised === $requestedQty => 'confirmed',
            $totalPromised > 0 => 'partial',
            default => 'rejected',
        };

        $digest = hash('sha256', implode('|', [
            $itemCode,
            (string) $availableAtpQty,
            (string) $availableCtpQty,
            (string) $requestedQty,
            (string) $atpConfirmedQty,
            (string) $ctpManufacturingQty,
            $promiseStatus,
        ]));

        return [
            'item_code' => $itemCode,
            'available_atp_qty' => $availableAtpQty,
            'available_ctp_qty' => $availableCtpQty,
            'atp_confirmed_qty' => $atpConfirmedQty,
            'ctp_manufacturing_qty' => $ctpManufacturingQty,
            'shortfall_qty' => $shortfallQty,
            'promise_status' => $promiseStatus,
            'certificate' => new OrderPromiseCertificate(
                itemCode: $itemCode,
                availableAtpQty: $availableAtpQty,
                availableCtpQty: $availableCtpQty,
                requestedQty: $requestedQty,
                atpConfirmedQty: $atpConfirmedQty,
                ctpManufacturingQty: $ctpManufacturingQty,
                promiseStatus: $promiseStatus,
                digest: $digest,
            ),
        ];
    }
}
