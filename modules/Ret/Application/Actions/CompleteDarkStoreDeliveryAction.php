<?php

declare(strict_types=1);

namespace Modules\Ret\Application\Actions;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Ret\Domain\Models\RetQuickCommerceOrder;
use Modules\Shared\Application\BaseAction;

class CompleteDarkStoreDeliveryAction extends BaseAction
{
    /**
     * @param  array<string, mixed>  $params
     */
    public function execute(array $params): RetQuickCommerceOrder
    {
        if (empty($params['idempotency_key'])) {
            throw new InvalidArgumentException('Idempotency-Key is required to complete delivery.');
        }

        $idempotencyKey = (string) $params['idempotency_key'];

        $existing = RetQuickCommerceOrder::where('idempotency_key', $idempotencyKey)->first();
        if ($existing && $existing->status === 'DELIVERED') {
            return $existing;
        }

        $qcOrder = RetQuickCommerceOrder::findOrFail($params['qc_order_id']);

        $podHash = (string) $params['pod_signature_hash'];
        if (strlen($podHash) !== 64 || ! ctype_xdigit($podHash)) {
            throw new InvalidArgumentException('POD signature hash must be a valid 64-character hexadecimal SHA-256 string.');
        }

        $deliveredAt = Carbon::parse($params['delivered_at']);

        return DB::transaction(function () use ($qcOrder, $deliveredAt, $podHash, $idempotencyKey) {
            $isLate = $deliveredAt->greaterThan($qcOrder->promised_delivery_at);
            $compensationMinor = 0;

            if ($isLate) {
                // Customer receives 25,000 IDR automatic compensation voucher
                $compensationMinor = 25_000;
            }

            $qcOrder->update([
                'status' => 'DELIVERED',
                'actual_delivered_at' => $deliveredAt,
                'pod_signature_hash' => $podHash,
                'is_sla_breached' => $isLate,
                'compensation_credit_minor' => $compensationMinor,
                'auto_credit_compensation_minor' => $compensationMinor,
                'compensation_issued' => $isLate,
                'idempotency_key' => $idempotencyKey,
            ]);

            $this->audit(
                action: 'ret_dark_store_delivery_completed',
                auditable: $qcOrder,
                newValues: [
                    'actual_delivered_at' => $deliveredAt->toIso8601String(),
                    'is_sla_breached' => $isLate,
                    'compensation_minor' => $compensationMinor,
                ],
                impactType: 'dark_store_fulfillment',
            );

            return $qcOrder;
        });
    }
}
