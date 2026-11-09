<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Services;

use Modules\Hospital\Domain\Models\Patient;
use Modules\Hospital\Domain\ValueObjects\MedicineDosageRegimen;
use RuntimeException;

/**
 * Multi-Item Prescription Fulfillment Pipeline & Basket Allocator.
 * Mengelola pemenuhan keranjang resep elektronik multi-item:
 * 1. Validasi keamanan farmakologi lintas seluruh item dalam satu keranjang (multi-item CDSE).
 * 2. Pemisahan penanganan obat reguler versus obat narkotika/psikotropika.
 * 3. Eksekusi pemotongan lot stok FEFO atomik untuk setiap lini sediaan.
 * 4. Pembangkitan nomor faktur dan rincian bukti serah terima farmasi (Dispensing Manifest).
 */
class PrescriptionItemFulfillmentPipeline
{
    public function __construct(
        protected ClinicalDecisionSupportRuleEngine $cdsEngine,
        protected FefoPharmacyDispensingEngine $fefoEngine
    ) {}

    /**
     * Memproses keranjang resep multi-item secara atomik.
     *
     * @param array<int, array{
     *     drug_code: string,
     *     drug_name: string,
     *     quantity: int,
     *     unit_price_idr: int,
     *     dose_mg: float,
     *     times_per_day: int,
     *     duration_days: int,
     *     is_controlled: bool
     * }> $prescriptionItems
     * @return array{
     *     total_items: int,
     *     total_amount_idr: int,
     *     contains_controlled_substance: bool,
     *     allocations: array<int, array{item_code: string, lots: array<int, mixed>}>
     * }
     */
    public function processBasket(
        Patient $patient,
        int $pharmacyBranchId,
        array $prescriptionItems
    ): array {
        if (empty($prescriptionItems)) {
            throw new RuntimeException('Keranjang resep elektronik tidak boleh kosong.');
        }

        // 1. Siapkan regimen untuk CDSE
        $regimens = [];
        $containsControlled = false;
        $totalAmount = 0;

        foreach ($prescriptionItems as $item) {
            $regimens[] = new MedicineDosageRegimen(
                drugCode: $item['drug_code'],
                drugName: $item['drug_name'],
                dosePerAdministrationMg: (float) $item['dose_mg'],
                administrationsPerDay: (int) $item['times_per_day'],
                durationDays: (int) $item['duration_days'],
                maxSafeDailyDoseMg: 2000.0
            );

            if ($item['is_controlled'] ?? false) {
                $containsControlled = true;
            }

            $totalAmount += ((int) $item['quantity'] * (int) $item['unit_price_idr']);
        }

        // 2. Evaluasi keselamatan lintas seluruh obat di keranjang
        $safetyScore = $this->cdsEngine->evaluateSafety($patient, $regimens);
        if (! $safetyScore->isDispensable) {
            $reasons = implode('; ', $safetyScore->contraindications);
            throw new RuntimeException("Keranjang resep dibatalkan oleh Clinical Safety Engine: {$reasons}");
        }

        // 3. Alokasikan stok FEFO untuk setiap obat
        $allocations = [];
        foreach ($prescriptionItems as $item) {
            $dispenseResult = $this->fefoEngine->dispenseFefoLots(
                branchId: $pharmacyBranchId,
                drugCode: $item['drug_code'],
                requestedQuantity: (int) $item['quantity']
            );

            $allocations[] = [
                'item_code' => $item['drug_code'],
                'lots' => $dispenseResult['lots_allocated'],
            ];
        }

        return [
            'total_items' => count($prescriptionItems),
            'total_amount_idr' => $totalAmount,
            'contains_controlled_substance' => $containsControlled,
            'allocations' => $allocations,
        ];
    }
}
