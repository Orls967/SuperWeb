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
use Modules\Resto\Domain\Enums\TransferStatus;
use Modules\Resto\Domain\Models\Ingredient;
use Modules\Resto\Domain\Models\IngredientCost;
use Modules\Resto\Domain\Models\Outlet;
use Modules\Resto\Domain\Models\StockTransfer;

class ShipStockTransferAction
{
    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly Ledger $ledger
    ) {}

    /**
     * @param  array<int, array{ingredient_id: int, qty: float|string}>  $lines
     */
    public function handle(
        int $fromOutletId,
        int $toOutletId,
        array $lines,
        User $shipper,
        ?string $note = null
    ): StockTransfer {
        if ($fromOutletId === $toOutletId) {
            throw new InvalidArgumentException('Outlet asal dan tujuan transfer tidak boleh sama.');
        }

        $fromOutlet = Outlet::findOrFail($fromOutletId);
        $toOutlet = Outlet::findOrFail($toOutletId);

        return DB::transaction(function () use ($fromOutlet, $toOutlet, $lines, $shipper, $note) {
            $dateStr = date('Ymd');
            $randomCode = strtoupper(Str::random(4));
            $number = "TRF-RSR-{$fromOutlet->code}-{$toOutlet->code}-{$dateStr}-{$randomCode}";

            $processedLines = [];
            $totalTransferCost = 0;
            $totalQtyShipped = BigDecimal::zero();

            foreach ($lines as $item) {
                $ing = Ingredient::findOrFail($item['ingredient_id']);
                $qtyBd = BigDecimal::of((string) $item['qty']);

                if ($qtyBd->isLessThanOrEqualTo(BigDecimal::zero())) {
                    continue;
                }

                // Check available stock
                $available = BigDecimal::of((string) $this->inventoryService->availableIngredient($ing->id, $fromOutlet->id));
                if ($available->isLessThan($qtyBd)) {
                    throw new InvalidArgumentException("Stok {$ing->name} di {$fromOutlet->name} tidak mencukupi (tersedia {$available}, diminta {$qtyBd}).");
                }

                // Get moving average cost at fromOutlet
                $costRecord = IngredientCost::where('ingredient_id', $ing->id)
                    ->where('outlet_id', $fromOutlet->id)
                    ->first();
                $unitCost = $costRecord ? BigDecimal::of((string) $costRecord->moving_avg_cost_per_base_unit) : BigDecimal::zero();
                $lineCost = $unitCost->multipliedBy($qtyBd)->toScale(0, RoundingMode::HalfUp)->toInt();

                $totalTransferCost += $lineCost;
                $totalQtyShipped = $totalQtyShipped->plus($qtyBd);

                // Deduct stock from origin
                $this->inventoryService->deductIngredient(
                    ingredientId: $ing->id,
                    outletId: $fromOutlet->id,
                    qtyBaseUnit: $qtyBd->__toString(),
                    reason: StockMovementReason::TRANSFER,
                    sourceType: 'resto_transfer',
                    sourceId: 0,
                    note: "Pengiriman transfer ke {$toOutlet->name}",
                    userId: $shipper->id
                );

                $processedLines[] = [
                    'ingredient_id' => $ing->id,
                    'name' => $ing->name,
                    'qty_shipped' => $qtyBd->__toString(),
                    'qty_received' => '0',
                    'unit_cost' => $unitCost->__toString(),
                    'line_cost' => $lineCost,
                ];
            }

            $transfer = StockTransfer::create([
                'uuid' => (string) Str::uuid(),
                'number' => $number,
                'from_outlet_id' => $fromOutlet->id,
                'to_outlet_id' => $toOutlet->id,
                'status' => TransferStatus::IN_TRANSIT,
                'shipped_at' => now(),
                'received_at' => null,
                'shipped_by' => $shipper->id,
                'received_by' => null,
                'lines' => $processedLines,
                'qty_shipped' => $totalQtyShipped->__toString(),
                'qty_received' => '0',
                'variance_note' => $note,
            ]);

            // Ledger posting: origin inventory -> transit inventory
            if ($totalTransferCost > 0) {
                $fromCode = $fromOutlet->code ?: "OUT-{$fromOutlet->id}";
                $fromInvCode = "inventory:resto:{$fromCode}:IDR";
                $transitCode = 'inventory:resto:transit:IDR';

                $this->ensureLedgerAccountExists($fromInvCode, "Persediaan Resto {$fromOutlet->name}", AccountKind::INVENTORY);
                $this->ensureLedgerAccountExists($transitCode, 'Persediaan Dalam Perjalanan (In Transit)', AccountKind::INVENTORY);

                $costBd = BigDecimal::of($totalTransferCost);

                $this->ledger->post(new PostingDTO(
                    type: TransactionType::PRODUCTION->value,
                    description: "Pengiriman transfer #{$transfer->number} dari {$fromOutlet->name} ke {$toOutlet->name}",
                    idempotencyKey: "resto:trf:ship:{$transfer->id}:".Str::uuid(),
                    entries: [
                        PostingEntryDTO::forCode($fromInvCode, 'IDR', $costBd->negated()),
                        PostingEntryDTO::forCode($transitCode, 'IDR', $costBd),
                    ],
                    referenceType: 'resto_transfer',
                    referenceId: $transfer->id,
                    createdBy: $shipper->id
                ));
            }

            return $transfer->load(['fromOutlet', 'toOutlet', 'shipper']);
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
