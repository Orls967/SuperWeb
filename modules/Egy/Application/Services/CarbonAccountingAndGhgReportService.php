<?php

declare(strict_types=1);

namespace Modules\Egy\Application\Services;

use Modules\Egy\Application\DTOs\GhgInventoryReportDTO;
use Modules\Egy\Domain\Models\CarbonCreditBatch;
use Modules\Egy\Domain\Models\RecCertificate;
use Modules\Egy\Domain\StateMachines\RecCertificateStateMachine;

/**
 * Layanan Penghitungan Emisi Gas Rumah Kaca (Scope 1, 2, 3) dan Rekonsiliasi Karbon Korporasi.
 * Mengacu pada standar ISO 14064-1 & GHG Protocol Corporate Standard.
 */
final class CarbonAccountingAndGhgReportService
{
    /**
     * Faktor emisi standar (integer basis points per unit):
     * - Genset solar diesel: 2680 gram CO2e / liter solar (2680 g/L = 2.68 kg/L)
     * - Grid PLN JAMALI (Jawa-Madura-Bali): 870 gram CO2e / kWh (0.87 kg CO2e / kWh)
     * - Grid Luar Jawa: 950 gram CO2e / kWh (0.95 kg CO2e / kWh)
     * - Air baku PDAM / WTP: 350 gram CO2e / m3 air terolah
     */
    public const EMISSION_FACTOR_DIESEL_GRAM_PER_LITER = 2680;

    public const EMISSION_FACTOR_GRID_PLN_GRAM_PER_KWH = 870;

    public const EMISSION_FACTOR_WATER_GRAM_PER_M3 = 350;

    /**
     * Hitung inventarisasi GRK komprehensif untuk satu entitas dan periode pelaporan.
     *
     * @param  int  $dieselFuelConsumedLiters  Konsumsi solar genset internal (Scope 1)
     * @param  int  $gridElectricityConsumedKwh  Konsumsi listrik dari grid eksternal (Scope 2 Location)
     * @param  int  $waterTreatedM3  Volume air baku terolah (Scope 3 Upstream)
     * @param  int  $wasteLandfillTonnageKg  Sampah non-daur ulang dikirim ke TPA (Scope 3 Downstream, 1.25 kg CO2e/kg sampah)
     */
    public function generateInventoryReport(
        string $reportingPeriod,
        string $entityIdentifier,
        int $dieselFuelConsumedLiters,
        int $gridElectricityConsumedKwh,
        int $waterTreatedM3,
        int $wasteLandfillTonnageKg
    ): GhgInventoryReportDTO {
        // Scope 1: Direct emissions pembakaran diesel genset
        $scope1DieselKgCo2e = (int) intdiv(
            $dieselFuelConsumedLiters * self::EMISSION_FACTOR_DIESEL_GRAM_PER_LITER,
            1000
        );
        $totalScope1Kg = $scope1DieselKgCo2e;

        // Scope 2: Location-based grid electricity emissions
        $scope2LocationKg = (int) intdiv(
            $gridElectricityConsumedKwh * self::EMISSION_FACTOR_GRID_PLN_GRAM_PER_KWH,
            1000
        );

        // Ambil sertifikat REC yang telah dipensiunkan (retired) untuk entitas ini pada periode terkait
        $retiredRecMwh = (int) RecCertificate::where('status', RecCertificateStateMachine::STATUS_RETIRED)
            ->where('beneficiary_entity', $entityIdentifier)
            ->sum('mwh_amount');

        // 1 MWh = 1000 kWh -> pengurangan emisi = retiredRecMwh * 1000 * 870 / 1000 = retiredRecMwh * 870 kg CO2e
        $recDeductionKg = $retiredRecMwh * self::EMISSION_FACTOR_GRID_PLN_GRAM_PER_KWH;
        $scope2MarketKg = max(0, $scope2LocationKg - $recDeductionKg);

        // Scope 3: Air olahan dan limbah TPA
        $scope3WaterKg = (int) intdiv(
            $waterTreatedM3 * self::EMISSION_FACTOR_WATER_GRAM_PER_M3,
            1000
        );
        $scope3WasteKg = (int) intdiv(
            $wasteLandfillTonnageKg * 1250, // 1250 gram CO2e per kg landfill
            1000
        );
        $totalScope3Kg = $scope3WaterKg + $scope3WasteKg;

        // Ambil kredit karbon (carbon offset) yang telah dipensiunkan secara permanen
        $offsetsRetiredKg = (int) CarbonCreditBatch::where('status', 'retired')->sum('retired_kg_co2e');

        // Neraca Bersih Emisi Karbon Grup (Net Carbon Balance):
        // Net = Scope 1 + Scope 2 Market + Scope 3 - Offsets Retired
        $grossEmissionsTotal = $totalScope1Kg + $scope2MarketKg + $totalScope3Kg;
        $netCarbonBalanceKg = max(0, $grossEmissionsTotal - $offsetsRetiredKg);

        $checksumPayload = sprintf(
            'GHG-INVENTORY|%s|%s|S1:%d|S2LOC:%d|REC_DED:%d|S2MKT:%d|S3:%d|OFFSETS:%d|NET:%d',
            $reportingPeriod,
            $entityIdentifier,
            $totalScope1Kg,
            $scope2LocationKg,
            $recDeductionKg,
            $scope2MarketKg,
            $totalScope3Kg,
            $offsetsRetiredKg,
            $netCarbonBalanceKg
        );
        $auditChecksum = hash('sha256', $checksumPayload);

        return new GhgInventoryReportDTO(
            reportingPeriod: $reportingPeriod,
            entityIdentifier: $entityIdentifier,
            grossScope1EmissionsKgCo2e: $totalScope1Kg,
            grossScope2LocationEmissionsKgCo2e: $scope2LocationKg,
            recDeductionsKgCo2e: $recDeductionKg,
            netScope2MarketEmissionsKgCo2e: $scope2MarketKg,
            grossScope3EmissionsKgCo2e: $totalScope3Kg,
            carbonOffsetsRetiredKgCo2e: $offsetsRetiredKg,
            netGroupCarbonBalanceKgCo2e: $netCarbonBalanceKg,
            scope1DetailsKgCo2e: [
                'diesel_genset_fuel_kg' => $scope1DieselKgCo2e,
            ],
            scope2DetailsKgCo2e: [
                'grid_pln_location_kg' => $scope2LocationKg,
                'rec_market_adjustment_kg' => -$recDeductionKg,
            ],
            scope3DetailsKgCo2e: [
                'water_treatment_kg' => $scope3WaterKg,
                'landfill_waste_kg' => $scope3WasteKg,
            ],
            auditChecksumSha256: $auditChecksum
        );
    }
}
