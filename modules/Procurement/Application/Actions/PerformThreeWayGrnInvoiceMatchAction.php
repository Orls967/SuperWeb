<?php

declare(strict_types=1);

namespace Modules\Procurement\Application\Actions;

use Modules\Procurement\Domain\Models\PurchaseOrder;
use Modules\Procurement\Domain\Models\ReceivingReport;
use Modules\Procurement\Domain\Models\SupplierInvoice;
use Modules\Procurement\Domain\Models\ThreeWayMatch;

class PerformThreeWayGrnInvoiceMatchAction
{
    /**
     * Perform 3-way match between PO, GRN (Receiving Report), and Supplier Invoice.
     * Tolerance allowed: 2% (200 basis points) price/qty variance.
     *
     * @return array{match: ThreeWayMatch, is_matched: bool, variance_idr: int, variance_pct: int}
     */
    public function execute(
        PurchaseOrder $po,
        ReceivingReport $grn,
        SupplierInvoice $invoice,
        int $tolerancePct = 2
    ): array {
        $poAmountIdr = (int) ($po->total_amount ?? $po->total_amount_idr ?? 0);
        $grnAmountIdr = (int) ($grn->received_amount ?? $grn->received_amount_idr ?? $poAmountIdr);
        $invoiceAmountIdr = (int) ($invoice->invoice_amount_idr ?? $invoice->total_amount_idr ?? 0);

        $priceVarianceIdr = abs($invoiceAmountIdr - $poAmountIdr);
        $variancePct = $poAmountIdr > 0 ? (int) round(($priceVarianceIdr / $poAmountIdr) * 100) : 0;

        $isMatched = $variancePct <= $tolerancePct;
        $result = $isMatched ? 'passed' : 'variance_flagged';

        $notes = $isMatched
            ? '3-Way match succeeded within tolerance.'
            : sprintf('Variance %d%% exceeds allowed %d%% threshold.', $variancePct, $tolerancePct);

        $match = ThreeWayMatch::create([
            'invoice_id' => (string) $invoice->id,
            'grn_id' => (int) $grn->id,
            'po_id' => (string) $po->id,
            'result' => $result,
            'po_amount_idr' => $poAmountIdr,
            'grn_amount_idr' => $grnAmountIdr,
            'invoice_amount_idr' => $invoiceAmountIdr,
            'price_variance_idr' => $priceVarianceIdr,
            'qty_variance_pct' => $variancePct,
            'notes' => $notes,
        ]);

        if ($isMatched) {
            $invoice->update(['status' => 'matched']);
        } else {
            $invoice->update(['status' => 'disputed']);
        }

        return [
            'match' => $match,
            'is_matched' => $isMatched,
            'variance_idr' => $priceVarianceIdr,
            'variance_pct' => $variancePct,
        ];
    }
}
