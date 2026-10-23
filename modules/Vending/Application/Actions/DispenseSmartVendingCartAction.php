<?php

declare(strict_types=1);

namespace Modules\Vending\Application\Actions;

use Modules\Shared\Application\BaseAction;
use Modules\Vending\Domain\Models\VendingTransaction;
use Modules\Vending\Domain\Models\VendingUnit;

class DispenseSmartVendingCartAction extends BaseAction
{
    /**
     * Dispense smart vending cart item, decrement stock, and record ledger revenue.
     *
     * @return array{transaction: VendingTransaction, tx_code: string, remaining_stock: int, total_price_idr: int}
     */
    public function handle(
        int $vendingUnitId,
        string $itemCode,
        int $qty,
        int $unitPriceIdr,
        string $paymentMethod = 'QRIS',
        ?string $biometricHash = null
    ): array {
        return $this->transaction(function () use (
            $vendingUnitId,
            $itemCode,
            $qty,
            $unitPriceIdr,
            $paymentMethod,
            $biometricHash
        ) {
            $unit = VendingUnit::where('id', $vendingUnitId)->lockForUpdate()->firstOrFail();

            if ($unit->current_stock < $qty) {
                throw new \DomainException("Vending machine {$unit->unit_code} has insufficient stock ({$unit->current_stock} remaining).");
            }

            $totalPriceIdr = $qty * $unitPriceIdr;
            $txCode = 'TX-VEN-'.date('YmdHis').'-'.rand(100, 999);

            $transaction = VendingTransaction::create([
                'tx_code' => $txCode,
                'vending_unit_id' => $unit->id,
                'item_code' => $itemCode,
                'qty' => (string) $qty,
                'price_idr' => (string) $totalPriceIdr,
                'payment_method' => $paymentMethod,
                'biometric_token_hash' => $biometricHash,
                'status' => 'settled',
            ]);

            $unit->current_stock -= $qty;
            $unit->total_sales_count += $qty;
            $unit->total_sales_idr += $totalPriceIdr;
            $unit->save();

            $idempotencyKey = hash('sha256', "vending:tx:{$txCode}");

            $this->audit(
                action: 'vending.cart.dispensed',
                auditable: $transaction,
                oldValues: null,
                newValues: [
                    'tx_code' => $txCode,
                    'unit_code' => $unit->unit_code,
                    'qty' => $qty,
                    'total_price_idr' => $totalPriceIdr,
                ],
                correlationId: "ven_tx_{$txCode}",
                impactType: 'vending_sale'
            );

            $this->outbox(
                eventType: 'VendingItemDispensed',
                payload: [
                    'tx_code' => $txCode,
                    'unit_id' => $unit->id,
                    'item_code' => $itemCode,
                    'qty' => $qty,
                    'total_price_idr' => $totalPriceIdr,
                    'remaining_stock' => $unit->current_stock,
                ],
                idempotencyKey: $idempotencyKey
            );

            return [
                'transaction' => $transaction,
                'tx_code' => $txCode,
                'remaining_stock' => $unit->current_stock,
                'total_price_idr' => $totalPriceIdr,
            ];
        });
    }
}
