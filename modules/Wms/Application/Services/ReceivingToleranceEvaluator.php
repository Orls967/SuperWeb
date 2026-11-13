<?php

declare(strict_types=1);

namespace Modules\Wms\Application\Services;

use InvalidArgumentException;
use Modules\Wms\Domain\Support\QuantityNormalizer;

/**
 * X41-1: Evaluator toleransi penerimaan barang inbound (integer bps, 100% = 10000 bps).
 */
final class ReceivingToleranceEvaluator
{
    public const string DECISION_WITHIN_TOLERANCE = 'within_tolerance';

    public const string DECISION_OVER_RECEIPT = 'over_receipt';

    public const string DECISION_SHORT_RECEIPT = 'short_receipt';

    /**
     * @return array{
     *     expected_qty_base: int,
     *     received_qty_base: int,
     *     variance_qty_base: int,
     *     variance_bps: int,
     *     decision: string,
     *     requires_supervisor_approval: bool
     * }
     */
    public function evaluate(
        int $expectedQtyBase,
        int $receivedQtyBase,
        int $overToleranceBps = 500,
        int $shortToleranceBps = 500,
    ): array {
        if ($expectedQtyBase <= 0 || $receivedQtyBase < 0) {
            throw new InvalidArgumentException('Kuantitas expected harus > 0 dan received >= 0.');
        }

        $varianceQty = $receivedQtyBase - $expectedQtyBase;
        $varianceBps = QuantityNormalizer::varianceBps($expectedQtyBase, $receivedQtyBase);

        if ($varianceBps > $overToleranceBps) {
            $decision = self::DECISION_OVER_RECEIPT;
            $requiresApproval = true;
        } elseif ($varianceBps < -$shortToleranceBps) {
            $decision = self::DECISION_SHORT_RECEIPT;
            $requiresApproval = true;
        } else {
            $decision = self::DECISION_WITHIN_TOLERANCE;
            $requiresApproval = false;
        }

        return [
            'expected_qty_base' => $expectedQtyBase,
            'received_qty_base' => $receivedQtyBase,
            'variance_qty_base' => $varianceQty,
            'variance_bps' => $varianceBps,
            'decision' => $decision,
            'requires_supervisor_approval' => $requiresApproval,
        ];
    }
}
