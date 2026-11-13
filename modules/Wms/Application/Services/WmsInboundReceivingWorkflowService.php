<?php

declare(strict_types=1);

namespace Modules\Wms\Application\Services;

use Modules\Wms\Application\Actions\ReceiveInboundShipmentAction;
use Modules\Wms\Domain\Services\CrossDockEligibilityEvaluator;
use Modules\Wms\Domain\Services\ReceivingDiscrepancyClassifier;

/**
 * Orkestrasi alur inbound receiving: evaluasi toleransi, klasifikasi deviasi, cek cross-dock, & catat GRN.
 */
final class WmsInboundReceivingWorkflowService
{
    public function __construct(
        private readonly ReceivingToleranceEvaluator $toleranceEvaluator,
        private readonly ReceivingDiscrepancyClassifier $classifier,
        private readonly CrossDockEligibilityEvaluator $crossDockEvaluator,
        private readonly ReceiveInboundShipmentAction $receiveAction,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<array{outbound_order_id: string, product_id: int, needed_qty: int, dock_ready: bool}>  $openBackorders
     * @return array{
     *     classification: array<string, mixed>,
     *     cross_dock: array<string, mixed>,
     *     receipt_result: array<string, mixed>
     * }
     */
    public function processInbound(array $payload, array $openBackorders = [], bool $hasDamagedPackaging = false): array
    {
        $eval = $this->toleranceEvaluator->evaluate(
            expectedQtyBase: (int) $payload['expected_qty_base'],
            receivedQtyBase: (int) $payload['received_qty_base'],
        );

        $classification = $this->classifier->classify(
            toleranceDecision: $eval['decision'],
            varianceBps: $eval['variance_bps'],
            hasDamagedPackaging: $hasDamagedPackaging,
        );

        $crossDock = $this->crossDockEvaluator->evaluate(
            productId: (int) $payload['product_id'],
            incomingQty: (int) $payload['received_qty_base'],
            openBackorders: $openBackorders,
        );

        $receiptResult = $this->receiveAction->execute($payload);

        return [
            'classification' => $classification,
            'cross_dock' => $crossDock,
            'receipt_result' => $receiptResult,
        ];
    }
}
