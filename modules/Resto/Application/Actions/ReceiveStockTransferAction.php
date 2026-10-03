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
use Modules\Banking\Domain\Models\LedgerTransaction;
use Modules\Inventory\Contracts\InventoryService;
use Modules\Inventory\Domain\Enums\StockMovementReason;
use Modules\Resto\Domain\Enums\TransferStatus;
use Modules\Resto\Domain\Models\IngredientCost;
use Modules\Resto\Domain\Models\StockTransfer;

class ReceiveStockTransferAction
{
    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly Ledger $ledger
    ) {}

    /**
     * @param  array<int, float|string>  $receivedQtys  [ingredient_id => qty_received]
     */
    public function handle(
        StockTransfer $transfer,
        array $receivedQtys,
        User $receiver,
        ?string $varianceNote = null
    ): StockTransfer {
        return DB::transaction(function () use ($transfer, $receivedQtys, $receiver, $varianceNote) {
            $lockedTransfer = StockTransfer::where('id', $transfer->id)->lockForUpdate()->firstOrFail();

            // Status must be re-checked AFTER the row lock, otherwise two concurrent
            // receivers can both pass the pre-transaction check and both credit stock.
            if ($lockedTransfer->status !== TransferStatus::IN_TRANSIT) {
                throw new InvalidArgumentException("Transfer #{$lockedTransfer->number} tidak berstatus in_transit.");
            }

            $ledgerKey = "resto:trf:recv:{$lockedTransfer->id}";
            if (LedgerTransaction::where('idempotency_key', $ledgerKey)->exists()) {
                // The ledger posting for this transfer already happened: a concurrent or
                // repeated receive has already credited the destination inventory.
                return $lockedTransfer->load(['fromOutlet', 'toOutlet', 'receiver']);
            }

            $toOutlet = $lockedTransfer->toOutlet;

            $updatedLines = [];
            $totalReceivedQty = BigDecimal::zero();
            $totalShippedCost = 0;
            $totalReceivedCost = 0;
            $totalDiscrepancyCost = 0;
            $hasDiscrepancy = false;

            foreach ($lockedTransfer->lines as $line) {
                $ingId = (int) $line['ingredient_id'];
                $shippedQty = BigDecimal::of((string) $line['qty_shipped']);
                $unitCost = BigDecimal::of((string) $line['unit_cost']);

                $receivedQty = isset($receivedQtys[$ingId])
                    ? BigDecimal::of((string) $receivedQtys[$ingId])
                    : $shippedQty;

                if ($receivedQty->isLessThan($shippedQty)) {
                    $hasDiscrepancy = true;
                }

                $shippedLineCost = $unitCost->multipliedBy($shippedQty)->toScale(0, RoundingMode::HalfUp)->toInt();
                $receivedLineCost = $unitCost->multipliedBy($receivedQty)->toScale(0, RoundingMode::HalfUp)->toInt();
                $discrepancyLineCost = max(0, $shippedLineCost - $receivedLineCost);

                $totalShippedCost += $shippedLineCost;
                $totalReceivedCost += $receivedLineCost;
                $totalDiscrepancyCost += $discrepancyLineCost;
                $totalReceivedQty = $totalReceivedQty->plus($receivedQty);

                // Add received stock to destination outlet
                if ($receivedQty->isGreaterThan(BigDecimal::zero())) {
                    $this->inventoryService->addIngredient(
                        ingredientId: $ingId,
                        outletId: $toOutlet->id,
                        qtyBaseUnit: $receivedQty->__toString(),
                        reason: StockMovementReason::TRANSFER,
                        sourceType: 'resto_transfer',
                        sourceId: $lockedTransfer->id,
                        note: "Penerimaan transfer #{$lockedTransfer->number}",
                        userId: $receiver->id
                    );

                    // Update moving average cost at destination outlet
                    $destCost = IngredientCost::where('ingredient_id', $ingId)
                        ->where('outlet_id', $toOutlet->id)
                        ->first();
                    $destStock = BigDecimal::of((string) $this->inventoryService->availableIngredient($ingId, $toOutlet->id))->minus($receivedQty);
                    $destUnitCost = $destCost ? BigDecimal::of((string) $destCost->moving_avg_cost_per_base_unit) : $unitCost;

                    if ($destStock->isLessThanOrEqualTo(BigDecimal::zero())) {
                        $newAvgCost = $unitCost;
                    } else {
                        $newAvgCost = $destStock->multipliedBy($destUnitCost)
                            ->plus($receivedQty->multipliedBy($unitCost))
                            ->dividedBy($destStock->plus($receivedQty), 6, RoundingMode::HalfUp);
                    }

                    IngredientCost::updateOrCreate(
                        ['ingredient_id' => $ingId, 'outlet_id' => $toOutlet->id],
                        [
                            'moving_avg_cost_per_base_unit' => $newAvgCost->__toString(),
                            'last_purchase_cost' => $unitCost->__toString(),
                            'updated_at' => now(),
                        ]
                    );
                }

                $updatedLines[] = [
                    'ingredient_id' => $ingId,
                    'name' => $line['name'],
                    'qty_shipped' => $shippedQty->__toString(),
                    'qty_received' => $receivedQty->__toString(),
                    'unit_cost' => $unitCost->__toString(),
                    'line_cost' => $receivedLineCost,
                ];
            }

            // Ledger postings
            if ($totalShippedCost > 0) {
                $toCode = $toOutlet->code ?: "OUT-{$toOutlet->id}";
                $toInvCode = "inventory:resto:{$toCode}:IDR";
                $transitCode = 'inventory:resto:transit:IDR';
                $wasteCode = 'expense:resto:waste:IDR';

                $this->ensureLedgerAccountExists($toInvCode, "Persediaan Resto {$toOutlet->name}", AccountKind::INVENTORY);
                $this->ensureLedgerAccountExists($transitCode, 'Persediaan Dalam Perjalanan (In Transit)', AccountKind::INVENTORY);

                $entries = [
                    PostingEntryDTO::forCode($transitCode, 'IDR', BigDecimal::of($totalShippedCost)->negated()),
                ];

                if ($totalReceivedCost > 0) {
                    $entries[] = PostingEntryDTO::forCode($toInvCode, 'IDR', BigDecimal::of($totalReceivedCost));
                }

                if ($totalDiscrepancyCost > 0) {
                    $this->ensureLedgerAccountExists($wasteCode, 'Beban Limbah/Waste Makanan Resto', AccountKind::EXPENSE);
                    $entries[] = PostingEntryDTO::forCode($wasteCode, 'IDR', BigDecimal::of($totalDiscrepancyCost));
                }

                // Balance adjustment if 1 IDR rounding artifact exists
                $sumCheck = BigDecimal::of($totalShippedCost)->negated()
                    ->plus(BigDecimal::of($totalReceivedCost))
                    ->plus(BigDecimal::of($totalDiscrepancyCost));

                if (! $sumCheck->isZero()) {
                    $entries[1] = PostingEntryDTO::forCode($toInvCode, 'IDR', BigDecimal::of($totalReceivedCost)->minus($sumCheck));
                }

                $this->ledger->post(new PostingDTO(
                    type: TransactionType::PRODUCTION->value,
                    description: "Penerimaan transfer #{$lockedTransfer->number} di {$toOutlet->name}".($hasDiscrepancy ? ' (Terdapat selisih)' : ''),
                    idempotencyKey: $ledgerKey,
                    entries: $entries,
                    referenceType: 'resto_transfer',
                    referenceId: $lockedTransfer->id,
                    createdBy: $receiver->id
                ));
            }

            $lockedTransfer->status = $hasDiscrepancy ? TransferStatus::DISCREPANCY : TransferStatus::RECEIVED;
            $lockedTransfer->received_at = now();
            $lockedTransfer->received_by = $receiver->id;
            $lockedTransfer->qty_received = $totalReceivedQty->__toString();
            $lockedTransfer->lines = $updatedLines;
            if ($varianceNote) {
                $lockedTransfer->variance_note = trim(($lockedTransfer->variance_note ? $lockedTransfer->variance_note.' | ' : '').$varianceNote);
            }
            $lockedTransfer->save();

            return $lockedTransfer->load(['fromOutlet', 'toOutlet', 'receiver']);
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
