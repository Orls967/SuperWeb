<?php

declare(strict_types=1);

namespace Modules\Ret\Application\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Ret\Domain\Models\RetOrder;
use Modules\Ret\Domain\Models\RetSeller;
use Modules\Ret\Domain\Models\RetSellerSettlement;
use Modules\Shared\Application\BaseAction;

class ProcessOrderRefundAction extends BaseAction
{
    public function __construct(
        protected LedgerService $ledgerService
    ) {}

    /**
     * @param  array<string, mixed>  $params
     * @return array{refund_amount_minor: int, commission_refund_minor: int, seller_ap_deduction_minor: int}
     */
    public function execute(array $params): array
    {
        if (empty($params['idempotency_key'])) {
            throw new InvalidArgumentException('Idempotency-Key is required to process order refund.');
        }

        $idempotencyKey = (string) $params['idempotency_key'];
        $order = RetOrder::findOrFail($params['order_id']);
        $refundAmount = isset($params['amount_minor']) ? (int) $params['amount_minor'] : (int) $order->total_amount_minor;
        $reason = (string) ($params['reason'] ?? 'Customer refund requested');

        return DB::transaction(function () use ($order, $refundAmount, $reason, $idempotencyKey) {
            $isSettled = $order->status === 'SETTLED' || RetSellerSettlement::where('order_id', $order->id)->exists();
            $commissionRefund = 0;
            $sellerApDeduction = 0;

            if ($isSettled) {
                $seller = $order->seller ?: RetSeller::findOrFail($order->seller_id);
                $commissionBps = $seller->commission_override_bps ?? 1000;
                $commissionRefund = (int) round(($refundAmount * $commissionBps) / 10000);
                $sellerApDeduction = $refundAmount - $commissionRefund;

                // Reversal postings: Debit commission, Debit seller AP, Credit receivable
                $this->ledgerService->post(new PostingDTO(
                    type: 'RETAIL_ORDER_REFUND',
                    description: "Post-settlement refund for order {$order->order_number}: {$reason}",
                    idempotencyKey: $idempotencyKey,
                    entries: Posting::lines()
                        ->debit('ret:commission_revenue:IDR', $commissionRefund)
                        ->debit('ret:seller_payable:IDR', $sellerApDeduction)
                        ->credit('ret:escrow_receivable:IDR', $refundAmount)
                        ->toEntries('IDR'),
                    referenceType: 'RET_REFUND',
                    referenceId: $order->order_number,
                ));

                // Append-only compensating settlement record for seller AP clawback (PR4/PR5)
                RetSellerSettlement::create([
                    'id' => (string) Str::uuid(),
                    'settlement_code' => 'CLAWBACK-'.strtoupper(Str::random(10)),
                    'order_id' => $order->id,
                    'seller_id' => $seller->id,
                    'seller_party_id' => $seller->party_id,
                    'verified_gmv_minor' => -$refundAmount,
                    'gross_order_amount_minor' => -$refundAmount,
                    'commission_rate_pct' => $commissionBps / 100.0,
                    'commission_amount_minor' => -$commissionRefund,
                    'commission_fee_minor' => -$commissionRefund,
                    'net_seller_payable_minor' => -$sellerApDeduction,
                    'net_payout_minor' => -$sellerApDeduction,
                    'status' => 'SETTLED',
                    'idempotency_key' => "{$idempotencyKey}:clawback",
                ]);
            } else {
                // Pre-settlement refund: reverse escrow
                $this->ledgerService->post(new PostingDTO(
                    type: 'RETAIL_ORDER_REFUND',
                    description: "Pre-settlement escrow reversal for order {$order->order_number}: {$reason}",
                    idempotencyKey: $idempotencyKey,
                    entries: Posting::lines()
                        ->debit('escrow:payment:IDR', $refundAmount)
                        ->credit('ret:escrow_receivable:IDR', $refundAmount)
                        ->toEntries('IDR'),
                    referenceType: 'RET_REFUND',
                    referenceId: $order->order_number,
                ));
            }

            $order->update(['status' => 'REFUNDED']);

            $this->audit(
                action: 'ret_order_refunded',
                auditable: $order,
                newValues: ['refund_amount' => $refundAmount, 'reason' => $reason, 'is_settled' => $isSettled],
                impactType: 'customer_refund',
            );

            return [
                'refund_amount_minor' => $refundAmount,
                'commission_refund_minor' => $commissionRefund,
                'seller_ap_deduction_minor' => $sellerApDeduction,
            ];
        });
    }
}
