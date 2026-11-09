<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Services;

use DomainException;

/**
 * Layanan Domain Kalkulasi Biaya Klinis Berbasis Aktivitas (Hospital Activity-Based Costing - ABC).
 * Menghitung biaya pokok pelayanan (Cost of Goods Sold / Cost of Care) riil per episode tindakan medis:
 *   1. Direct Material Costs (Bahan Medis Habis Pakai / BMHP, obat-obatan, implan).
 *   2. Direct Labor Costs (Jasa profesi dokter DPJP, operator bedah, anestesiolog, perawat instrumen).
 *   3. Equipment Capital Depreciation (Penyusutan per menit mesin medis canggih: Cath Lab, CT Scan, Ventilator).
 *   4. Overhead & Facility Allocation (Sterilisasi instrumen CSSD, pengolahan limbah B3, utilitas ruang bedah).
 *   5. Analisis Varians Tarif (Hospital Actual Unit Cost vs INA-CBGs Package Tariff).
 */
class HospitalClinicalCostingService
{
    /**
     * Tarif penyusutan modal per menit instrumen dan mesin medis utama.
     *
     * @var array<string, int>
     */
    private const EQUIPMENT_DEPRECIATION_RATE_PER_MINUTE_IDR = [
        'CATH_LAB_ANGIOGRAPHY' => 45_000,
        'CT_SCAN_128_SLICE' => 35_000,
        'MRI_1_5_TESLA' => 50_000,
        'HEMODIALYSIS_MACHINE' => 8_000,
        'ICU_VENTILATOR_ADVANCED' => 12_000,
        'OPERATING_MICROSCOPE' => 25_000,
        'LAPAROSCOPY_TOWER' => 20_000,
        'ULTRASOUND_4D' => 6_000,
        'STANDARD_OR_TABLE_MONITOR' => 5_000,
    ];

    /**
     * Tarif dasar alokasi overhead fasilitas per jam ruangan.
     *
     * @var array<string, int>
     */
    private const FACILITY_OVERHEAD_RATE_PER_HOUR_IDR = [
        'MAJOR_OPERATING_THEATER' => 750_000,
        'MINOR_PROCEDURE_ROOM' => 250_000,
        'ICU_ISOLATION_ROOM' => 450_000,
        'ENDOSCOPY_SUITE' => 350_000,
        'GENERAL_INPATIENT_WARD' => 120_000,
        'CHEMOTHERAPY_DAYCARE' => 200_000,
    ];

    /**
     * Menghitung rincian biaya pokok riil dari suatu prosedur atau episode klinis.
     *
     * @param  array<int, array{item_code: string, description: string, quantity: float, unit_cost_idr: int}>  $consumables
     * @param  array<int, array{role: string, personnel_name: string, fee_idr: int}>  $staffFees
     * @param  string  $primaryEquipmentCode  Kode mesin medis utama yang digunakan
     * @param  int  $equipmentDurationMinutes  Durasi penggunaan mesin dalam menit
     * @param  string  $facilityRoomType  Tipe ruangan fasilitas (e.g. MAJOR_OPERATING_THEATER)
     * @param  int  $facilityDurationHours  Durasi pemakaian ruangan dalam jam
     * @param  int  $benchmarkInaCbgTariffIdr  Tarif paket INA-CBGs pembanding
     * @return array<string, mixed>
     */
    public function calculateProcedureUnitCost(
        array $consumables,
        array $staffFees,
        string $primaryEquipmentCode,
        int $equipmentDurationMinutes,
        string $facilityRoomType,
        int $facilityDurationHours,
        int $benchmarkInaCbgTariffIdr
    ): array {
        if ($equipmentDurationMinutes < 0 || $facilityDurationHours < 0) {
            throw new DomainException('Durasi penggunaan alat atau ruangan tidak boleh negatif.');
        }

        // 1. Biaya Bahan Medis Habis Pakai (Direct Consumables & Drugs)
        $totalMaterialCost = 0;
        $materialDetails = [];
        foreach ($consumables as $item) {
            $qty = max(0.0, (float) $item['quantity']);
            $unitCost = max(0, (int) $item['unit_cost_idr']);
            $subtotal = (int) round($qty * $unitCost);
            $totalMaterialCost += $subtotal;
            $materialDetails[] = [
                'item_code' => $item['item_code'],
                'description' => $item['description'],
                'quantity' => $qty,
                'unit_cost_idr' => $unitCost,
                'subtotal_idr' => $subtotal,
            ];
        }

        // 2. Biaya Tenaga Medis Langsung (Direct Professional Fees)
        $totalLaborCost = 0;
        foreach ($staffFees as $staff) {
            $fee = max(0, (int) $staff['fee_idr']);
            $totalLaborCost += $fee;
        }

        // 3. Penyusutan Alat Medis (Equipment Depreciation)
        $eqRatePerMinute = self::EQUIPMENT_DEPRECIATION_RATE_PER_MINUTE_IDR[strtoupper($primaryEquipmentCode)] ?? 5_000;
        $totalEquipmentCost = $eqRatePerMinute * $equipmentDurationMinutes;

        // 4. Biaya Fasilitas & Alokasi Overhead (CSSD, Limbah B3, Energi)
        $facRatePerHour = self::FACILITY_OVERHEAD_RATE_PER_HOUR_IDR[strtoupper($facilityRoomType)] ?? 150_000;
        $totalFacilityCost = $facRatePerHour * max(1, $facilityDurationHours);

        // Biaya penanganan limbah medis B3 per prosedur bedah (flat rate Rp 175.000)
        $hazardousWasteDisposalCost = 175_000;
        $totalOverheadCost = $totalFacilityCost + $hazardousWasteDisposalCost;

        // Total Biaya Pokok Produksi (True Unit Cost)
        $trueUnitCostIdr = $totalMaterialCost + $totalLaborCost + $totalEquipmentCost + $totalOverheadCost;

        // 5. Analisis Varians terhadap Tarif INA-CBGs
        $varianceIdr = $benchmarkInaCbgTariffIdr - $trueUnitCostIdr;
        $costToTariffRatio = $benchmarkInaCbgTariffIdr > 0 ? round(($trueUnitCostIdr / $benchmarkInaCbgTariffIdr) * 100, 2) : 0.0;

        $profitabilityStatus = 'HEALTHY_SURPLUS';
        if ($varianceIdr < 0) {
            $profitabilityStatus = 'DEFICIT_OVERSPENDING';
        } elseif ($costToTariffRatio > 85.0) {
            $profitabilityStatus = 'NARROW_MARGIN_WARNING';
        }

        return [
            'total_unit_cost_idr' => $trueUnitCostIdr,
            'direct_materials_cost_idr' => $totalMaterialCost,
            'direct_labor_cost_idr' => $totalLaborCost,
            'equipment_depreciation_cost_idr' => $totalEquipmentCost,
            'facility_overhead_cost_idr' => $totalOverheadCost,
            'benchmark_ina_cbg_tariff_idr' => $benchmarkInaCbgTariffIdr,
            'variance_margin_idr' => $varianceIdr,
            'cost_to_tariff_ratio_pct' => $costToTariffRatio,
            'profitability_status' => $profitabilityStatus,
            'material_item_count' => count($materialDetails),
            'staff_count' => count($staffFees),
        ];
    }
}
