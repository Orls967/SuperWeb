<?php

declare(strict_types=1);

namespace Modules\Ret\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Ret\Domain\Models\RetInventoryItem;
use Modules\Shared\Application\BaseAction;

class ReconcileStoreShrinkageAndInventoryAction extends BaseAction
{
    public function __construct(
        protected LedgerService $ledgerService
    ) {}

    /**
     * @return array{sku: string, physical_count: int, system_count: int, shrinkage_qty: int, write_off_minor: int}
     */
    public function execute(string $sku, int $physicalCount): array
    {
        $item = RetInventoryItem::where('sku', $sku)->firstOrFail();

        return DB::transaction(function () use ($item, $physicalCount) {
            $systemCount = (int) $item->stock_available;
            $shrinkageQty = max(0, $systemCount - $physicalCount);
            $writeOffMinor = $shrinkageQty * (int) $item->map_price_minor;

            if ($shrinkageQty > 0) {
                $item->update(['stock_available' => $physicalCount]);

                $idempotencyKey = hash('sha256', "SHRINKAGE:{$item->sku}:{$shrinkageQty}:{$writeOffMinor}");
                $this->ledgerService->post(new PostingDTO(
                    type: 'RETAIL_SHRINKAGE_WRITE_OFF',
                    description: "Inventory shrinkage write off for SKU {$item->sku} ({$shrinkageQty} units)",
                    idempotencyKey: $idempotencyKey,
                    entries: Posting::lines()
                        ->debit('ret:inventory_shrinkage_expense:IDR', $writeOffMinor)
                        ->credit('ret:inventory_asset:IDR', $writeOffMinor)
                        ->toEntries('IDR'),
                    referenceType: 'RET_INVENTORY_ITEM',
                    referenceId: $item->sku,
                ));
            }

            $this->audit(
                action: 'ret_inventory_shrinkage_reconciled',
                auditable: $item,
                newValues: ['physical' => $physicalCount, 'shrinkage' => $shrinkageQty, 'loss_minor' => $writeOffMinor],
                impactType: 'inventory_integrity',
            );

            return [
                'sku' => $item->sku,
                'physical_count' => $physicalCount,
                'system_count' => $systemCount,
                'shrinkage_qty' => $shrinkageQty,
                'write_off_minor' => $writeOffMinor,
            ];
        });
    }
}
