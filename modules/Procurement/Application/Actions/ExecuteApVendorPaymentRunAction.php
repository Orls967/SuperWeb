<?php

declare(strict_types=1);

namespace Modules\Procurement\Application\Actions;

use Modules\Procurement\Domain\Models\PaymentBatch;
use Modules\Procurement\Domain\Models\PaymentItem;
use Modules\Procurement\Domain\Models\SupplierInvoice;

class ExecuteApVendorPaymentRunAction
{
    /**
     * Build and execute AP vendor payment run batch.
     *
     * @param  array<int, array{invoice: SupplierInvoice, early_discount_bps?: int}>  $invoicesData
     * @return array{batch: PaymentBatch, net_payable_idr: int, total_discount_idr: int}
     */
    public function execute(
        string $batchNumber,
        array $invoicesData,
        string $scheduledDate,
        ?int $userId = null
    ): array {
        $totalGrossIdr = 0;
        $totalDiscountIdr = 0;

        $batch = PaymentBatch::create([
            'number' => $batchNumber,
            'status' => 'scheduled',
            'total_amount_idr' => 0,
            'item_count' => count($invoicesData),
            'scheduled_date' => $scheduledDate,
            'created_by_user_id' => $userId,
        ]);

        foreach ($invoicesData as $item) {
            /** @var SupplierInvoice $inv */
            $inv = $item['invoice'];
            $discountBps = (int) ($item['early_discount_bps'] ?? 0);

            $grossAmount = (int) ($inv->invoice_amount_idr ?? $inv->total_amount_idr ?? 0);
            $discountAmount = (int) intdiv($grossAmount * $discountBps, 10000);
            $netAmount = max(0, $grossAmount - $discountAmount);

            $totalGrossIdr += $grossAmount;
            $totalDiscountIdr += $discountAmount;

            PaymentItem::create([
                'batch_id' => (int) $batch->id,
                'invoice_id' => (string) $inv->id,
                'amount_idr' => $netAmount,
                'early_discount_idr' => $discountAmount,
                'status' => 'pending',
            ]);

            $inv->update(['status' => 'scheduled_for_payment']);
        }

        $netPayableIdr = max(0, $totalGrossIdr - $totalDiscountIdr);

        $batch->update([
            'total_amount_idr' => $netPayableIdr,
            'status' => 'executed',
        ]);

        return [
            'batch' => $batch->fresh(['items']),
            'net_payable_idr' => $netPayableIdr,
            'total_discount_idr' => $totalDiscountIdr,
        ];
    }
}
