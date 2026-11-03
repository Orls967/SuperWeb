<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Application\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Core\Contracts\ClockInterface;
use Modules\Manufacturing\Contracts\RecycledMaterialReceiver;
use Modules\Manufacturing\Domain\Models\Material;
use Modules\Manufacturing\Domain\Models\MaterialLot;
use Modules\Manufacturing\Ledger\ManufacturingTransactionTypes;

class RecycledMaterialReceiverService implements RecycledMaterialReceiver
{
    public function __construct(
        protected ?LedgerService $ledgerService = null,
        protected ?ClockInterface $clock = null
    ) {
        $this->ledgerService = $ledgerService ?? app(LedgerService::class);
        $this->clock = $clock ?? app(ClockInterface::class);
    }

    public function receive(
        int $reverseShipmentId,
        string $materialCode,
        string $gradeCode,
        int $quantityGrams,
        int $unitPricePerKgIdr,
        string $idempotencyKey
    ): int {
        return DB::transaction(function () use (
            $reverseShipmentId,
            $materialCode,
            $gradeCode,
            $quantityGrams,
            $unitPricePerKgIdr,
            $idempotencyKey
        ): int {
            // Cek idempotensi pada receipt yang sudah ada
            $existing = DB::table('mfg_recycled_receipts')
                ->where('idempotency_key', $idempotencyKey)
                ->orWhere('reverse_shipment_id', $reverseShipmentId)
                ->first();

            if ($existing) {
                return (int) $existing->id;
            }

            // Hitung nilai lot: round_half_up(gram * harga_per_kg / 1.000)
            $totalValueIdr = (int) round(($quantityGrams * $unitPricePerKgIdr) / 1000, 0, PHP_ROUND_HALF_UP);

            // Pastikan entitas material tersedia
            $material = Material::firstOrCreate(
                ['code' => $materialCode],
                [
                    'name' => 'Recycled '.strtoupper($materialCode),
                    'uom' => 'KG',
                    'standard_cost_idr' => $unitPricePerKgIdr,
                ]
            );

            $now = $this->clock ? $this->clock->now() : CarbonImmutable::now();
            $producedDate = $now->toDateString();
            $lotNumber = 'LOT-REV-'.$reverseShipmentId.'-'.substr(hash('sha256', $idempotencyKey), 0, 8);

            // Buat tepat 1 mfg_material_lots
            $lot = MaterialLot::create([
                'material_id' => $material->id,
                'lot_number' => $lotNumber,
                'grade_code' => $gradeCode,
                'qty' => $quantityGrams / 1000,
                'unit_cost_idr' => $unitPricePerKgIdr,
                'produced_at' => $producedDate,
                'source_type' => 'reverse_shipment',
                'source_ref' => (string) $reverseShipmentId,
                'source_reverse_shipment_id' => $reverseShipmentId,
                'status' => 'active',
            ]);

            $receiptId = DB::table('mfg_recycled_receipts')->insertGetId([
                'reverse_shipment_id' => $reverseShipmentId,
                'material_lot_id' => $lot->id,
                'material_code' => $materialCode,
                'grade_code' => $gradeCode,
                'quantity_grams' => $quantityGrams,
                'unit_price_per_kg_idr' => $unitPricePerKgIdr,
                'total_value_idr' => $totalValueIdr,
                'idempotency_key' => $idempotencyKey,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // D79-3 Posting:
            // Debit: inv:materials:IDR
            // Kredit: mfg:recycled_material_clearing:IDR
            if ($totalValueIdr > 0) {
                $this->ledgerService->post(new PostingDTO(
                    type: ManufacturingTransactionTypes::RECYCLED_MATERIAL_INTAKE->value,
                    description: "Penerimaan material daur ulang shipment {$reverseShipmentId}",
                    idempotencyKey: "mfg:recycled_receipt:{$reverseShipmentId}",
                    entries: Posting::lines()
                        ->debit('inv:materials:IDR', $totalValueIdr)
                        ->credit('mfg:recycled_material_clearing:IDR', $totalValueIdr)
                        ->toEntries('IDR'),
                    referenceType: 'REVERSE_SHIPMENT',
                    referenceId: (string) $reverseShipmentId,
                ));
            }

            return (int) $receiptId;
        });
    }
}
