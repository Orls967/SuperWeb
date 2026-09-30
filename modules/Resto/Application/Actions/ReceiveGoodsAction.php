<?php

declare(strict_types=1);

namespace Modules\Resto\Application\Actions;

use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Domain\Enums\AccountKind;
use Modules\Banking\Domain\Enums\TransactionType;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Inventory\Contracts\InventoryService;
use Modules\Inventory\Domain\Enums\StockMovementReason;
use Modules\Resto\Domain\Enums\POStatus;
use Modules\Resto\Domain\Enums\ReceiptQuality;
use Modules\Resto\Domain\Models\GoodsReceipt;
use Modules\Resto\Domain\Models\GoodsReceiptLine;
use Modules\Resto\Domain\Models\IngredientCost;
use Modules\Resto\Domain\Models\PurchaseOrder;
use Modules\Resto\Domain\Models\PurchaseOrderLine;

class ReceiveGoodsAction
{
    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly Ledger $ledger
    ) {}

    /**
     * @param  array<int, array{po_line_id: int, qty_received_base_unit: float|string, unit_cost?: float|string|null}>  $linesData
     */
    public function handle(
        PurchaseOrder $po,
        array $linesData,
        User $receiver,
        string $quality = 'good',
        ?string $note = null,
        ?string $photoRef = null
    ): GoodsReceipt {
        if ($po->status === POStatus::RECEIVED || $po->status === POStatus::CANCELLED) {
            throw new InvalidArgumentException("PO #{$po->number} sudah berstatus {$po->status->value} dan tidak dapat diterima lagi.");
        }

        return DB::transaction(function () use ($po, $linesData, $receiver, $quality, $note, $photoRef) {
            $receipt = GoodsReceipt::create([
                'uuid' => (string) Str::uuid(),
                'po_id' => $po->id,
                'received_at' => now(),
                'received_by' => $receiver->id,
                'note' => $note,
                'quality' => ReceiptQuality::tryFrom($quality) ?? ReceiptQuality::GOOD,
                'photo_ref' => $photoRef,
            ]);

            $totalReceiptCost = 0;

            foreach ($linesData as $data) {
                $poLine = PurchaseOrderLine::where('id', $data['po_line_id'])
                    ->where('po_id', $po->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $qtyReceivedBd = BigDecimal::of((string) $data['qty_received_base_unit']);
                if ($qtyReceivedBd->isLessThanOrEqualTo(BigDecimal::zero())) {
                    continue;
                }

                // Calculate unit cost per base unit from PO line if not provided
                $unitCostBd = isset($data['unit_cost']) && $data['unit_cost'] !== null
                    ? BigDecimal::of((string) $data['unit_cost'])
                    : BigDecimal::of((string) $poLine->line_total)
                        ->dividedBy(BigDecimal::of((string) ($poLine->qty_base_unit ?: '1')), 6, RoundingMode::HalfUp);

                $lineTotalCost = $unitCostBd->multipliedBy($qtyReceivedBd)->toScale(0, RoundingMode::HalfUp)->toInt();
                $totalReceiptCost += $lineTotalCost;

                // 1. Create GoodsReceiptLine
                GoodsReceiptLine::create([
                    'receipt_id' => $receipt->id,
                    'po_line_id' => $poLine->id,
                    'qty_received_base_unit' => $qtyReceivedBd->__toString(),
                    'unit_cost' => $unitCostBd->__toString(),
                    'total_cost' => $lineTotalCost,
                ]);

                // 2. Update PO line qty_received
                $newQtyReceived = BigDecimal::of($poLine->qty_received)->plus($qtyReceivedBd);
                $poLine->qty_received = $newQtyReceived->__toString();
                $poLine->save();

                // 3. Add stock via InventoryService
                $this->inventoryService->addIngredient(
                    ingredientId: $poLine->ingredient_id,
                    outletId: $po->outlet_id,
                    qtyBaseUnit: $qtyReceivedBd->__toString(),
                    reason: StockMovementReason::PURCHASE,
                    sourceType: 'resto_purchase_order',
                    sourceId: $po->id,
                    note: "Penerimaan PO #{$po->number} (Receipt #{$receipt->id})",
                    userId: $receiver->id
                );

                // 4. Update Moving Average Cost in resto_ingredient_costs (BigDecimal precision)
                $oldCostRecord = IngredientCost::where('ingredient_id', $poLine->ingredient_id)
                    ->where('outlet_id', $po->outlet_id)
                    ->lockForUpdate()
                    ->first();

                $oldStock = BigDecimal::of((string) $this->inventoryService->availableIngredient($poLine->ingredient_id, $po->outlet_id))
                    ->minus($qtyReceivedBd); // prior stock before this addition
                $oldCost = $oldCostRecord ? BigDecimal::of((string) $oldCostRecord->moving_avg_cost_per_base_unit) : $unitCostBd;

                if ($oldStock->isLessThanOrEqualTo(BigDecimal::zero())) {
                    $newAvgCost = $unitCostBd->toScale(6, RoundingMode::HalfUp);
                } else {
                    $totalOldValue = $oldStock->multipliedBy($oldCost);
                    $totalNewValue = $qtyReceivedBd->multipliedBy($unitCostBd);
                    $totalStock = $oldStock->plus($qtyReceivedBd);
                    $newAvgCost = $totalOldValue->plus($totalNewValue)->dividedBy($totalStock, 6, RoundingMode::HalfUp);
                }

                IngredientCost::updateOrCreate(
                    ['ingredient_id' => $poLine->ingredient_id, 'outlet_id' => $po->outlet_id],
                    [
                        'moving_avg_cost_per_base_unit' => $newAvgCost->__toString(),
                        'last_purchase_cost' => $unitCostBd->__toString(),
                        'updated_at' => now(),
                    ]
                );
            }

            // 5. Update PO status (fully received vs partially received)
            $allReceived = true;
            foreach ($po->lines()->get() as $line) {
                if (! $line->isFullyReceived()) {
                    $allReceived = false;
                    break;
                }
            }

            $po->status = $allReceived ? POStatus::RECEIVED : POStatus::PARTIALLY_RECEIVED;
            $po->save();

            // 6. Double-entry ledger posting
            if ($totalReceiptCost > 0) {
                $outlet = $po->outlet;
                $outletCode = $outlet?->code ?: "OUT-{$po->outlet_id}";
                $invAccCode = "inventory:resto:{$outletCode}:IDR";
                $apAccCode = "ap:supplier:{$po->supplier_id}:IDR";

                $this->ensureLedgerAccountExists($invAccCode, "Persediaan Resto {$outlet?->name}", AccountKind::INVENTORY);
                $this->ensureLedgerAccountExists($apAccCode, "Utang Usaha Supplier #{$po->supplier_id} ({$po->supplier?->name})", AccountKind::AP);

                $costBd = BigDecimal::of($totalReceiptCost);

                $this->ledger->post(new PostingDTO(
                    type: TransactionType::SUPPLIER_PAYABLE->value,
                    description: "Penerimaan bahan PO #{$po->number} dari {$po->supplier?->name}",
                    idempotencyKey: "resto:po:receive:{$receipt->id}:".Str::uuid(),
                    entries: [
                        PostingEntryDTO::forCode($invAccCode, 'IDR', $costBd),
                        PostingEntryDTO::forCode($apAccCode, 'IDR', $costBd->negated()),
                    ],
                    referenceType: 'resto_purchase_order',
                    referenceId: $po->id,
                    createdBy: $receiver->id
                ));
            }

            return $receipt->load(['lines.poLine.ingredient', 'receiver']);
        });
    }

    private function ensureLedgerAccountExists(string $code, string $name, AccountKind $kind): LedgerAccount
    {
        return LedgerAccount::firstOrCreate(
            ['code' => $code],
            [
                'uuid' => (string) Str::uuid(),
                'name' => $name,
                'asset_code' => 'IDR',
                'kind' => $kind->value,
                'allow_negative' => true,
                'cached_balance' => '0',
                'is_frozen' => false,
            ]
        );
    }
}
