<?php

declare(strict_types=1);

namespace Modules\Circular\Application\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Circular\Domain\Models\CirOrder;
use Modules\Circular\Ledger\CircularSubjectAccounts;
use Modules\Circular\Ledger\CircularTransactionTypes;
use Modules\Manufacturing\Contracts\RecycledMaterialReceiver;

final class RecordOrderPlantReceiptAction
{
    public function __construct(
        protected RecycledMaterialReceiver $materialReceiver,
        protected LedgerService $ledgerService
    ) {}

    public function execute(
        CirOrder|int $order,
        int $acceptedGrams,
        string $gradeCode = 'A',
        ?string $idempotencyKey = null,
        ?User $actor = null
    ): CirOrder {
        if ($acceptedGrams <= 0) {
            throw ValidationException::withMessages([
                'accepted_grams' => 'Berat penerimaan faktual harus lebih besar dari 0 gram.',
            ]);
        }

        $orderModel = $order instanceof CirOrder ? $order : CirOrder::findOrFail($order);
        $key = $idempotencyKey ?? "cir:receipt:{$orderModel->id}";

        return DB::transaction(function () use ($orderModel, $acceptedGrams, $gradeCode, $key): CirOrder {
            $locked = CirOrder::where('id', $orderModel->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === 'received' || $locked->status === 'settled') {
                return $locked;
            }

            if ($acceptedGrams > $locked->ordered_grams) {
                throw ValidationException::withMessages([
                    'accepted_grams' => 'Berat diterima tidak boleh melebihi berat pesanan.',
                ]);
            }

            $reverseShipmentId = (int) ($locked->reverse_shipment_id ?? $locked->id);

            // Panggil K1 RecycledMaterialReceiver untuk membuat lot bahan baku dan mencatat penerimaan
            $plantReceiptId = $this->materialReceiver->receive(
                reverseShipmentId: $reverseShipmentId,
                materialCode: $locked->material_code,
                gradeCode: $gradeCode,
                quantityGrams: $acceptedGrams,
                unitPricePerKgIdr: $locked->unit_price_per_kg_idr,
                idempotencyKey: "k1:{$key}"
            );

            // D24-2 & D24-4: Hitung susut timbangan dan proses refund ke pembeli bila accepted_grams < ordered_grams
            $shortageGrams = max(0, $locked->ordered_grams - $acceptedGrams);
            $refundAmountIdr = 0;

            if ($shortageGrams > 0) {
                $refundAmountIdr = (int) round(
                    ($shortageGrams * $locked->unit_price_per_kg_idr) / 1000,
                    0,
                    PHP_ROUND_HALF_UP
                );
            }

            if ($refundAmountIdr > 0) {
                $buyerWalletCode = CircularSubjectAccounts::userWallet($locked->buyer_party_id);

                $this->ledgerService->post(new PostingDTO(
                    type: CircularTransactionTypes::CIRCULAR_SHRINKAGE_REFUND->value,
                    description: "Refund susut timbangan {$shortageGrams}g untuk order {$locked->order_number}",
                    idempotencyKey: "cir:refund:shrinkage:{$locked->id}:{$key}",
                    entries: Posting::lines()
                        ->debit('escrow:payment:IDR', $refundAmountIdr)
                        ->credit($buyerWalletCode, $refundAmountIdr)
                        ->toEntries('IDR'),
                    referenceType: 'CIRCULAR_ORDER',
                    referenceId: (string) $locked->id,
                ));
            }

            $locked->update([
                'accepted_grams' => $acceptedGrams,
                'grade_code' => $gradeCode,
                'plant_receipt_id' => $plantReceiptId,
                'refund_amount_idr' => $refundAmountIdr,
                'status' => 'received',
            ]);

            return $locked;
        });
    }
}
