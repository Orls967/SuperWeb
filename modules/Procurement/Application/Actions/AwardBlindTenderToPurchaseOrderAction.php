<?php

declare(strict_types=1);

namespace Modules\Procurement\Application\Actions;

use InvalidArgumentException;
use Modules\Procurement\Domain\Models\PoLine;
use Modules\Procurement\Domain\Models\PurchaseOrder;
use Modules\Procurement\Domain\Models\Tender;
use Modules\Procurement\Domain\Models\TenderBid;
use Modules\Shared\Application\BaseAction;

final class AwardBlindTenderToPurchaseOrderAction extends BaseAction
{
    /**
     * Awards sealed tender bid and generates formal Purchase Order in integer minor units.
     */
    public function execute(
        Tender $tender,
        TenderBid $winningBid,
        string $poTitle,
        ?string $expectedDate = null
    ): PurchaseOrder {
        if ($winningBid->tender_id !== $tender->id) {
            throw new InvalidArgumentException('Penawaran tender (bid) tidak sesuai dengan dokumen tender.');
        }

        if ($tender->status === 'awarded' || $tender->status === 'cancelled') {
            throw new InvalidArgumentException("Tender {$tender->number} sudah dalam status {$tender->status}.");
        }

        return $this->transaction(function () use ($tender, $winningBid, $poTitle, $expectedDate) {
            // Update tender bids
            $tender->bids()->where('id', '!=', $winningBid->id)->update(['is_winner' => false]);
            $winningBid->update(['is_winner' => true]);
            $tender->update(['status' => 'awarded']);

            $poNumber = 'PO-TND-'.date('Ymd').'-'.strtoupper(substr(md5((string) mt_rand()), 0, 6));
            $totalAmountIdr = (int) ($winningBid->amount_idr ?? 10_000_000);

            $po = PurchaseOrder::create([
                'number' => $poNumber,
                'supplier_id' => $winningBid->supplier_id,
                'tender_id' => $tender->id,
                'title' => $poTitle,
                'kind' => 'standard',
                'status' => 'approved',
                'version' => 1,
                'currency' => 'IDR',
                'total_amount' => $totalAmountIdr,
                'received_amount' => 0,
                'expected_date' => $expectedDate ?? now()->addDays(30)->toDateString(),
                'created_by_user_id' => auth()->id() ?? 1,
            ]);

            PoLine::create([
                'po_id' => $po->id,
                'description' => $poTitle,
                'qty' => 1,
                'unit' => 'lot',
                'unit_price' => $totalAmountIdr,
                'line_total' => $totalAmountIdr,
                'received_qty' => 0,
            ]);

            $this->audit('procurement.tender_awarded_to_po', $po, null, [
                'tender_id' => $tender->id,
                'winning_bid_id' => $winningBid->id,
                'total_amount_idr' => $totalAmountIdr,
            ]);

            return $po->load('lines');
        });
    }
}
