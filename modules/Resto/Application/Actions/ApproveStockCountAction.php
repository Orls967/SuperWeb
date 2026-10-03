<?php

declare(strict_types=1);

namespace Modules\Resto\Application\Actions;

use App\Models\User;
use Brick\Math\BigDecimal;
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
use Modules\Resto\Domain\Enums\StockCountStatus;
use Modules\Resto\Domain\Models\StockCount;

class ApproveStockCountAction
{
    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly Ledger $ledger
    ) {}

    public function handle(StockCount $stockCount, User $approver): StockCount
    {
        if (! in_array($approver->role, ['admin', 'outlet_manager'], true)) {
            throw new InvalidArgumentException('Hanya Outlet Manager atau Admin yang berhak menyetujui Stock Opname.');
        }

        if ($stockCount->status === StockCountStatus::APPROVED) {
            return $stockCount;
        }

        return DB::transaction(function () use ($stockCount, $approver) {
            $lockedCount = StockCount::where('id', $stockCount->id)->lockForUpdate()->firstOrFail();

            // Re-check under the lock: the caller's model may be stale, and without this
            // two concurrent approvals would both apply the variance adjustment.
            if ($lockedCount->status === StockCountStatus::APPROVED) {
                return $lockedCount->load(['lines.ingredient', 'approver', 'counter']);
            }

            // Deterministic key per stock count; if it is already on the ledger, this
            // approval was posted before, so no second adjustment or posting.
            $ledgerKey = "resto:opname:approve:{$lockedCount->id}";
            if (LedgerTransaction::where('idempotency_key', $ledgerKey)->exists()) {
                return $lockedCount->load(['lines.ingredient', 'approver', 'counter']);
            }

            $outlet = $lockedCount->outlet;
            $outletCode = $outlet->code ?: "OUT-{$outlet->id}";

            $netVarianceValue = 0;

            foreach ($lockedCount->lines as $line) {
                $varianceBd = BigDecimal::of((string) $line->variance);

                if ($varianceBd->isZero()) {
                    continue;
                }

                $netVarianceValue += $line->variance_value;

                if ($varianceBd->isNegative()) {
                    // Stock loss / shrinkage
                    $lossQty = $varianceBd->abs();
                    $this->inventoryService->deductIngredient(
                        ingredientId: $line->ingredient_id,
                        outletId: $outlet->id,
                        qtyBaseUnit: $lossQty->__toString(),
                        reason: StockMovementReason::ADJUSTMENT,
                        sourceType: 'resto_stock_count',
                        sourceId: $lockedCount->id,
                        note: "Penyesuaian Opname #{$lockedCount->id} (Selisih Kurang)",
                        userId: $approver->id
                    );
                } else {
                    // Stock surplus
                    $this->inventoryService->addIngredient(
                        ingredientId: $line->ingredient_id,
                        outletId: $outlet->id,
                        qtyBaseUnit: $varianceBd->__toString(),
                        reason: StockMovementReason::ADJUSTMENT,
                        sourceType: 'resto_stock_count',
                        sourceId: $lockedCount->id,
                        note: "Penyesuaian Opname #{$lockedCount->id} (Selisih Lebih)",
                        userId: $approver->id
                    );
                }
            }

            // Post net variance value to ledger
            if ($netVarianceValue !== 0) {
                $invAccCode = "inventory:resto:{$outletCode}:IDR";
                $wasteAccCode = 'expense:resto:waste:IDR';

                $this->ensureLedgerAccountExists($invAccCode, "Persediaan Resto {$outlet->name}", AccountKind::INVENTORY);
                $this->ensureLedgerAccountExists($wasteAccCode, 'Beban Limbah/Waste Makanan Resto', AccountKind::EXPENSE);

                $netValBd = BigDecimal::of($netVarianceValue);

                $this->ledger->post(new PostingDTO(
                    type: TransactionType::WASTE->value,
                    description: "Penyesuaian nilai persediaan hasil Stock Opname #{$lockedCount->id} ({$outlet->name})",
                    idempotencyKey: $ledgerKey,
                    entries: [
                        PostingEntryDTO::forCode($invAccCode, 'IDR', $netValBd),
                        PostingEntryDTO::forCode($wasteAccCode, 'IDR', $netValBd->negated()),
                    ],
                    referenceType: 'resto_stock_count',
                    referenceId: $lockedCount->id,
                    createdBy: $approver->id
                ));
            }

            $lockedCount->status = StockCountStatus::APPROVED;
            $lockedCount->approved_by = $approver->id;
            $lockedCount->approved_at = now();
            $lockedCount->save();

            return $lockedCount->load(['lines.ingredient', 'approver', 'counter']);
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
