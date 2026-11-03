<?php

declare(strict_types=1);

namespace Modules\Circular\Application\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Circular\Domain\Models\CirListing;
use Modules\Circular\Domain\Models\CirOrder;
use Modules\Circular\Domain\Models\CirOrderLine;
use Modules\Circular\Ledger\CircularSubjectAccounts;
use Modules\Circular\Ledger\CircularTransactionTypes;
use Modules\Logistics\Application\Actions\Reverse\CreateReverseShipmentAction;

final class PlaceOrderAction
{
    public function __construct(
        protected LedgerService $ledgerService,
        protected CreateReverseShipmentAction $createReverseShipment
    ) {}

    /**
     * @param array{
     *     listing_id: int,
     *     buyer_party_id: int,
     *     ordered_grams: int,
     *     destination_facility_id?: int|null,
     *     auction_round_id?: int|null,
     *     override_unit_price_per_kg_idr?: int|null,
     *     idempotency_key?: string|null
     * } $data
     */
    public function execute(array $data, ?User $actor = null): CirOrder
    {
        $idempotencyKey = trim((string) ($data['idempotency_key'] ?? ''));
        if ($idempotencyKey === '') {
            throw ValidationException::withMessages([
                'idempotency_key' => 'Idempotency-Key wajib disertakan untuk pemesanan marketplace sirkular.',
            ]);
        }

        return DB::transaction(function () use ($data, $idempotencyKey, $actor): CirOrder {
            $existing = CirOrder::where('idempotency_key', $idempotencyKey)->first();
            if ($existing !== null) {
                return $existing;
            }

            $listing = CirListing::where('id', $data['listing_id'])->lockForUpdate()->firstOrFail();

            $orderedGrams = (int) $data['ordered_grams'];
            if ($orderedGrams <= 0) {
                throw ValidationException::withMessages([
                    'ordered_grams' => 'Kuantitas pesanan harus lebih besar dari 0 gram.',
                ]);
            }

            if ($orderedGrams > $listing->available_grams || $listing->status !== 'active') {
                throw ValidationException::withMessages([
                    'ordered_grams' => 'Kuantitas pesanan melebihi stok tersedia pada listing.',
                ]);
            }

            $unitPricePerKgIdr = (int) ($data['override_unit_price_per_kg_idr'] ?? $listing->unit_price_per_kg_idr);
            $escrowAmountIdr = (int) round(($orderedGrams * $unitPricePerKgIdr) / 1000, 0, PHP_ROUND_HALF_UP);

            $originLocationId = (int) ($listing->origin_location_id ?? 0);
            $destinationFacilityId = (int) ($data['destination_facility_id'] ?? $originLocationId);

            // Kurangi stok listing
            $remainingGrams = $listing->available_grams - $orderedGrams;
            $listing->update([
                'available_grams' => $remainingGrams,
                'status' => $remainingGrams === 0 ? 'sold' : 'active',
            ]);

            // Buat Reverse Shipment di modul Logistics dengan reverse_reason = material_sekunder (D24-2)
            $shipment = $this->createReverseShipment->execute([
                'reverse_reason' => 'material_sekunder',
                'source_party_id' => $listing->seller_party_id,
                'material_code' => $listing->material_code,
                'estimated_weight_grams' => $orderedGrams,
                'origin_location_id' => (int) $originLocationId,
                'destination_facility_id' => (int) $destinationFacilityId,
                'consignee_name' => 'Fasilitas Pembeli Marketplace Sirkular',
            ], $actor);

            $seed = hash('sha256', "CIR-ORD:{$idempotencyKey}:{$listing->id}:{$data['buyer_party_id']}");
            $orderNumber = 'CIR-ORD-'.strtoupper(substr($seed, 0, 10));

            $order = CirOrder::create([
                'order_number' => $orderNumber,
                'listing_id' => $listing->id,
                'auction_round_id' => $data['auction_round_id'] ?? $listing->auction_round_id,
                'buyer_party_id' => (int) $data['buyer_party_id'],
                'seller_party_id' => $listing->seller_party_id,
                'material_code' => $listing->material_code,
                'grade_code' => $listing->grade_code,
                'ordered_grams' => $orderedGrams,
                'accepted_grams' => null,
                'unit_price_per_kg_idr' => $unitPricePerKgIdr,
                'escrow_amount_idr' => $escrowAmountIdr,
                'refund_amount_idr' => 0,
                'status' => 'in_transit',
                'origin_location_id' => (int) $originLocationId,
                'destination_facility_id' => (int) $destinationFacilityId,
                'reverse_shipment_id' => $shipment->id,
                'plant_receipt_id' => null,
                'idempotency_key' => $idempotencyKey,
            ]);

            CirOrderLine::create([
                'order_id' => $order->id,
                'line_type' => 'material_charge',
                'material_code' => $listing->material_code,
                'grade_code' => $listing->grade_code,
                'quantity_grams' => $orderedGrams,
                'unit_price_per_kg_idr' => $unitPricePerKgIdr,
                'subtotal_idr' => $escrowAmountIdr,
            ]);

            // D24-4 Posting Escrow Hold:
            // Debit: wallet:user:{pembeli}:IDR
            // Kredit: escrow:payment:IDR
            $buyerWalletCode = CircularSubjectAccounts::userWallet((int) $data['buyer_party_id']);

            if ($escrowAmountIdr > 0) {
                $this->ledgerService->post(new PostingDTO(
                    type: CircularTransactionTypes::CIRCULAR_ESCROW_HOLD->value,
                    description: "Circular Marketplace escrow hold untuk order {$order->order_number}",
                    idempotencyKey: "cir:escrow:hold:{$order->id}:{$idempotencyKey}",
                    entries: Posting::lines()
                        ->debit($buyerWalletCode, $escrowAmountIdr)
                        ->credit('escrow:payment:IDR', $escrowAmountIdr)
                        ->toEntries('IDR'),
                    referenceType: 'CIRCULAR_ORDER',
                    referenceId: (string) $order->id,
                ));
            }

            return $order;
        });
    }
}
