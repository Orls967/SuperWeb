<?php

declare(strict_types=1);

namespace Modules\Vending\Application\Services;

use DomainException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Vending\Domain\Models\VendingItem;
use Modules\Vending\Domain\Models\VendingTransaction;
use Modules\Vending\Domain\Models\VendingUnit;

class VendingDispenseService
{
    public function __construct(
        protected LedgerService $ledgerService
    ) {}

    /**
     * Dispense item from smart vending machine, decrement inventory, and post ledger revenue & COGS.
     *
     * @throws InvalidArgumentException|DomainException
     */
    public function dispenseItem(
        int $unitId,
        string $itemCode,
        int $qty,
        string $paymentMethod,
        string $idempotencyKey,
        ?string $biometricToken = null
    ): VendingTransaction {
        if (trim($idempotencyKey) === '') {
            throw new InvalidArgumentException('Idempotency key is required.');
        }

        return DB::transaction(function () use ($unitId, $itemCode, $qty, $paymentMethod, $idempotencyKey, $biometricToken): VendingTransaction {
            $unit = VendingUnit::where('id', $unitId)->lockForUpdate()->firstOrFail();

            if ($unit->status !== 'active' && $unit->status !== 'ACTIVE') {
                throw new DomainException('Vending unit is not active for dispensing');
            }

            if ($unit->current_stock < $qty) {
                throw new DomainException("Insufficient stock in vending unit ({$unit->current_stock} remaining).");
            }

            $item = VendingItem::where('item_code', $itemCode)->first();
            $unitPriceIdr = $item ? (int) $item->base_price_idr : 15_000;
            $unitCostIdr = $item ? (int) $item->cost_price_idr : 8_000;

            $totalPriceIdr = $qty * $unitPriceIdr;
            $totalCogsIdr = $qty * $unitCostIdr;

            // Decrement unit stock & increment counters
            $unit->decrement('current_stock', $qty);
            $unit->increment('total_sales_count', $qty);
            $unit->increment('total_sales_idr', $totalPriceIdr);

            $biometricHash = $biometricToken ? hash('sha256', $biometricToken) : null;
            $txCode = 'VTX-'.date('Ymd').'-'.strtoupper(substr(hash('sha256', $idempotencyKey), 0, 8));

            $transaction = VendingTransaction::create([
                'tx_code' => $txCode,
                'vending_unit_id' => $unit->id,
                'item_code' => $itemCode,
                'qty' => $qty,
                'price_idr' => $totalPriceIdr,
                'cogs_idr' => $totalCogsIdr,
                'payment_method' => $paymentMethod,
                'biometric_token_hash' => $biometricHash,
                'status' => 'COMPLETED',
                'idempotency_key' => $idempotencyKey,
            ]);

            // Post sales revenue ledger
            $this->ledgerService->post(new PostingDTO(
                type: 'vending_sales_revenue',
                description: "Sales Revenue for Vending Tx {$txCode}",
                idempotencyKey: "vnd:rev:{$txCode}",
                entries: Posting::lines()
                    ->debit('ven:clearing:IDR', $totalPriceIdr)
                    ->credit('vnd:sales_revenue:IDR', $totalPriceIdr)
                    ->toEntries('IDR'),
                referenceType: 'vending_transaction',
                referenceId: (string) $transaction->id
            ));

            // Post COGS ledger
            if ($totalCogsIdr > 0) {
                $this->ledgerService->post(new PostingDTO(
                    type: 'vending_cogs_expense',
                    description: "COGS for Vending Tx {$txCode}",
                    idempotencyKey: "vnd:cogs:{$txCode}",
                    entries: Posting::lines()
                        ->debit('expense:resto:cogs:IDR', $totalCogsIdr)
                        ->credit('inventory:resto:central:IDR', $totalCogsIdr)
                        ->toEntries('IDR'),
                    referenceType: 'vending_transaction',
                    referenceId: (string) $transaction->id
                ));
            }

            return $transaction;
        });
    }
}
