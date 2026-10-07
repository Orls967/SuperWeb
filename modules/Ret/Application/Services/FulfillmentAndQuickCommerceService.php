<?php

declare(strict_types=1);

namespace Modules\Ret\Application\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Ret\Domain\Models\RetCrowdshippingTask;
use Modules\Ret\Domain\Models\RetPackagingDeposit;
use Modules\Ret\Domain\Models\RetQuickCommerceOrder;
use RuntimeException;

class FulfillmentAndQuickCommerceService
{
    public function __construct(
        protected LedgerService $ledgerService
    ) {}

    /**
     * 139.1 Quick commerce order & SLA Promise Time Tracking.
     * Test (a): Promise breach -> automatic credit voucher 1x idempotent.
     * Test (d): Picking time per order recorded accurately.
     */
    public function createQuickCommerceOrder(array $params): RetQuickCommerceOrder
    {
        $placedAt = Carbon::parse($params['placed_at']);
        $promisedAt = $placedAt->copy()->addMinutes(30); // 30-minute promise

        return RetQuickCommerceOrder::create([
            'id' => (string) Str::uuid(),
            'order_code' => $params['order_code'] ?? 'QC-'.strtoupper(Str::random(8)),
            'dark_store_id' => $params['dark_store_id'],
            'customer_id' => $params['customer_id'],
            'placed_at' => $placedAt,
            'promised_delivery_at' => $promisedAt,
            'picking_duration_seconds' => (int) ($params['picking_duration_seconds'] ?? 180),
            'is_sla_breached' => false,
            'auto_credit_compensation_minor' => 0,
            'compensation_issued' => false,
            'status' => 'IN_FULFILLMENT',
        ]);
    }

    public function recordDeliveryArrival(string $orderCode, Carbon $actualDeliveredAt): RetQuickCommerceOrder
    {
        $order = RetQuickCommerceOrder::where('order_code', $orderCode)->firstOrFail();
        $order->actual_delivered_at = $actualDeliveredAt;

        $isBreached = $actualDeliveredAt->gt($order->promised_delivery_at);
        $order->is_sla_breached = $isBreached;

        if ($isBreached && ! $order->compensation_issued) {
            // Auto credit compensation: 25,000 IDR (2,500,000 minor) voucher
            $compensation = 2500000;
            $order->auto_credit_compensation_minor = $compensation;
            $order->compensation_issued = true;

            // Balanced ledger entry for SLA breach voucher
            $this->ledgerService->post(new PostingDTO(
                type: 'RETAIL_SLA_BREACH_COMPENSATION',
                description: "Automatic voucher credit for breached Q-Commerce delivery {$order->order_code}",
                idempotencyKey: 'RET-BREACH-'.$order->order_code,
                entries: [
                    PostingEntryDTO::forCode('ret:sla_compensation_expense:IDR', 'IDR', $compensation),
                    PostingEntryDTO::forCode('ret:customer_credit_liability:IDR', 'IDR', -$compensation),
                ],
                referenceType: 'QC_ORDER',
                referenceId: $order->order_code,
            ));
        }

        $order->status = 'DELIVERED';
        $order->save();

        return $order;
    }

    /**
     * 139.3 Crowdshipping driver tasks & fee cap rule.
     * Test (c): crowdshipper fee <= 30% order value rules.
     */
    public function assignCrowdshipping(array $params): RetCrowdshippingTask
    {
        $orderVal = (int) $params['order_value_minor'];
        $fee = (int) $params['delivery_fee_minor'];

        // Maximum crowdshipping fee is capped at 30% of order value
        $maxFee = (int) round($orderVal * 0.30);
        if ($fee > $maxFee) {
            throw new RuntimeException("Crowdshipping fee ({$fee}) exceeds maximum allowed rule ({$maxFee} = 30% of order value).");
        }

        $podPayload = $params['order_code'].'|'.$params['crowd_driver_id'].'|'.Carbon::now()->toIso8601String();
        $podHash = hash('sha256', $podPayload);

        return RetCrowdshippingTask::create([
            'id' => (string) Str::uuid(),
            'task_code' => 'CSH-'.strtoupper(Str::random(8)),
            'order_code' => $params['order_code'],
            'crowd_driver_id' => $params['crowd_driver_id'],
            'order_value_minor' => $orderVal,
            'delivery_fee_minor' => $fee,
            'pod_hash' => $podHash,
            'status' => 'COMPLETED',
        ]);
    }

    /**
     * 139.4 Reusable Packaging Deposits & Reverse Loop.
     * Test (b): Deposit kemasan sum == circulating packaging count * unit deposit.
     */
    public function issueReusablePackaging(string $customerId, string $pkgType, int $depositAmountMinor): RetPackagingDeposit
    {
        return DB::transaction(function () use ($customerId, $pkgType, $depositAmountMinor) {
            $code = 'PKG-'.strtoupper(Str::random(8));

            // Balanced deposit liability posting
            $this->ledgerService->post(new PostingDTO(
                type: 'RETAIL_PACKAGING_DEPOSIT_RECEIPT',
                description: "Deposit received for reusable packaging {$code}",
                idempotencyKey: 'RET-'.$code,
                entries: [
                    PostingEntryDTO::forCode('ret:packaging_deposit_cash:IDR', 'IDR', $depositAmountMinor),
                    PostingEntryDTO::forCode('ret:packaging_deposit_liability:IDR', 'IDR', -$depositAmountMinor),
                ],
                referenceType: 'PACKAGING_DEPOSIT',
                referenceId: $code,
            ));

            return RetPackagingDeposit::create([
                'id' => (string) Str::uuid(),
                'deposit_code' => $code,
                'customer_id' => $customerId,
                'packaging_type' => $pkgType,
                'deposit_amount_minor' => $depositAmountMinor,
                'status' => 'CIRCULATING',
            ]);
        });
    }

    public function returnReusablePackaging(string $depositCode): RetPackagingDeposit
    {
        $deposit = RetPackagingDeposit::where('deposit_code', $depositCode)->firstOrFail();

        if ($deposit->status !== 'CIRCULATING') {
            throw new RuntimeException("Packaging deposit {$depositCode} is not in circulating status.");
        }

        return DB::transaction(function () use ($deposit) {
            $deposit->status = 'RETURNED_REFUNDED';
            $deposit->save();

            // Reverse deposit liability
            $this->ledgerService->post(new PostingDTO(
                type: 'RETAIL_PACKAGING_DEPOSIT_REFUND',
                description: "Deposit refund for returned reusable packaging {$deposit->deposit_code}",
                idempotencyKey: 'RET-REFUND-'.$deposit->deposit_code,
                entries: [
                    PostingEntryDTO::forCode('ret:packaging_deposit_liability:IDR', 'IDR', $deposit->deposit_amount_minor),
                    PostingEntryDTO::forCode('ret:packaging_deposit_cash:IDR', 'IDR', -$deposit->deposit_amount_minor),
                ],
                referenceType: 'PACKAGING_DEPOSIT',
                referenceId: $deposit->deposit_code,
            ));

            return $deposit;
        });
    }

    public function calculateTotalCirculatingDeposits(): int
    {
        return (int) RetPackagingDeposit::where('status', 'CIRCULATING')->sum('deposit_amount_minor');
    }
}
