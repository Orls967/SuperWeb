<?php

declare(strict_types=1);

namespace Modules\Logistics\Application\Actions\Reverse;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Core\Contracts\ClockInterface;
use Modules\Esg\Contracts\EmissionBenefitRecorder;
use Modules\Logistics\Application\Actions\RecordTrackingEventAction;
use Modules\Logistics\Domain\Enums\ShipmentStatus;
use Modules\Logistics\Domain\Models\Shipment;
use Modules\Logistics\Ledger\SubjectAccounts;
use Modules\Manufacturing\Contracts\RecycledMaterialReceiver;
use Modules\Manufacturing\Ledger\ManufacturingTransactionTypes;
use Modules\Pricing\Contracts\MaterialPriceQuote;

class ProcessPlantIntakeAction
{
    public function __construct(
        protected RecycledMaterialReceiver $materialReceiver,
        protected MaterialPriceQuote $priceQuote,
        protected EmissionBenefitRecorder $emissionRecorder,
        protected RecordTrackingEventAction $recordTrackingEvent,
        protected LedgerService $ledgerService,
        protected ?ClockInterface $clock = null
    ) {
        $this->clock = $clock ?? app(ClockInterface::class);
    }

    /**
     * Penerimaan material daur ulang di pabrik (D79-2).
     *
     * @throws ValidationException Bila harga material belum ditetapkan (HTTP 422)
     */
    public function execute(
        Shipment $shipment,
        int $netWeightGrams,
        string $gradeCode,
        ?User $actor = null,
        ?string $idempotencyKey = null
    ): array {
        if ($netWeightGrams <= 0) {
            throw ValidationException::withMessages([
                'net_weight_grams' => 'Berat neto timbang harus berupa integer gram positif.',
            ]);
        }

        $now = $this->clock ? $this->clock->now() : CarbonImmutable::now();
        $materialCode = $shipment->material_code ?? 'SCRAP';

        // 1. D79-2: Dapatkan harga/kg dari K2 MaterialPriceQuote
        $pricePerKg = $this->priceQuote->pricePerKgIdr($materialCode, $gradeCode, $now);

        if ($pricePerKg === null) {
            throw ValidationException::withMessages([
                'price' => 'harga material belum ditetapkan',
            ]);
        }

        $key = $idempotencyKey ?? "lgx:reverse:intake:{$shipment->id}";

        return DB::transaction(function () use (
            $shipment,
            $netWeightGrams,
            $gradeCode,
            $materialCode,
            $pricePerKg,
            $key,
            $actor,
            $now
        ): array {
            $locked = Shipment::where('id', $shipment->id)->lockForUpdate()->firstOrFail();

            // 2. D79-2: Panggil K1 untuk membuat tepat SATU lot bahan baku
            $lotId = $this->materialReceiver->receive(
                reverseShipmentId: $locked->id,
                materialCode: $materialCode,
                gradeCode: $gradeCode,
                quantityGrams: $netWeightGrams,
                unitPricePerKgIdr: $pricePerKg,
                idempotencyKey: $key
            );

            // 3. D79-4: Catat manfaat emisi via K3
            $avoidedCo2eGrams = $this->emissionRecorder->recordAvoidedEmission(
                sourceType: 'reverse_shipment',
                sourceId: $locked->id,
                materialCode: $materialCode,
                quantityGrams: $netWeightGrams,
                at: $now,
                idempotencyKey: "esg:reverse:{$locked->id}"
            );

            // 4. D79-3: Catat Nota Sumber (Supplier Payable)
            // Nilai lot = round_half_up(gram * harga_per_kg / 1.000)
            $totalValueIdr = (int) round(($netWeightGrams * $pricePerKg) / 1000, 0, PHP_ROUND_HALF_UP);
            $partyId = $locked->source_party_id ?? $locked->shipper_id;

            if ($totalValueIdr > 0 && $partyId) {
                $apCode = SubjectAccounts::supplierPayable((int) $partyId);
                $this->ledgerService->post(new PostingDTO(
                    type: ManufacturingTransactionTypes::RECYCLED_SUPPLIER_PAYABLE->value,
                    description: "Nota sumber supplier {$partyId} untuk reverse shipment {$locked->id}",
                    idempotencyKey: "mfg:recycled_payable:{$locked->id}",
                    entries: Posting::lines()
                        ->debit('mfg:recycled_material_clearing:IDR', $totalValueIdr)
                        ->credit($apCode, $totalValueIdr)
                        ->toEntries('IDR'),
                    referenceType: 'REVERSE_SHIPMENT',
                    referenceId: (string) $locked->id,
                ));
            }

            // Perbarui status shipment menjadi received
            $locked->update([
                'status' => ShipmentStatus::Received,
                'net_weight_grams' => $netWeightGrams,
                'grade_code' => $gradeCode,
                'delivered_at' => $now,
            ]);

            // Catat event kustodi
            $this->recordTrackingEvent->execute(
                shipment: $locked,
                eventType: 'reverse.received',
                locationId: $locked->destination_facility_id,
                actor: $actor,
                actorRole: $actor ? $actor->role : 'plant_receiver',
                description: "Material daur ulang {$materialCode} grade {$gradeCode} diterima di pabrik (Lot ID {$lotId})",
                payload: [
                    'material_lot_id' => $lotId,
                    'net_weight_grams' => $netWeightGrams,
                    'grade_code' => $gradeCode,
                    'unit_price_per_kg_idr' => $pricePerKg,
                    'total_value_idr' => $totalValueIdr,
                    'avoided_co2e_grams' => $avoidedCo2eGrams,
                ],
                occurredAt: $now
            );

            return [
                'shipment_id' => $locked->id,
                'lot_id' => $lotId,
                'net_weight_grams' => $netWeightGrams,
                'grade_code' => $gradeCode,
                'unit_price_per_kg_idr' => $pricePerKg,
                'total_value_idr' => $totalValueIdr,
                'avoided_co2e_grams' => $avoidedCo2eGrams,
                'status' => ShipmentStatus::Received->value,
            ];
        });
    }
}

