<?php

declare(strict_types=1);

namespace Modules\Egy\Application\Services;

use Modules\Egy\Application\DTOs\BiogasWteProductionReportDTO;
use Modules\Egy\Application\DTOs\DistrictCoolingPerformanceReportDTO;
use Modules\Egy\Application\DTOs\IwaWaterAuditReportDTO;
use Modules\Egy\Domain\Models\DistrictCoolingPlant;
use Modules\Egy\Domain\Models\WasteTreatmentFacility;
use Modules\Egy\Domain\Models\WaterNetwork;
use Modules\Shared\Domain\Algorithms\Utility\BiogasDigesterYieldCalculator;
use Modules\Shared\Domain\Algorithms\Utility\ChillerCopEfficiencyAnalyzer;
use Modules\Shared\Domain\Algorithms\Utility\WaterBalance;

/**
 * Service untuk agregasi, audit, dan pembuatan laporan numerik utilitas kawasan.
 * Menghasilkan data terstruktur untuk IWA Water Balance, DCS Chiller Performance,
 * serta Biogas WTE Conversion.
 */
final class DistrictUtilityReportingService
{
    public function __construct(
        private readonly WaterBalance $waterBalance,
        private readonly ChillerCopEfficiencyAnalyzer $chillerCopAnalyzer,
        private readonly BiogasDigesterYieldCalculator $biogasCalculator,
    ) {}

    /**
     * Hasilkan laporan IWA Water Balance untuk seluruh jaringan pipa DMA kawasan.
     *
     * @return array<IwaWaterAuditReportDTO>
     */
    public function generateIwaWaterAuditReports(): array
    {
        $networks = WaterNetwork::with('nodes')->get();
        $reports = [];

        foreach ($networks as $net) {
            $inputL = (int) $net->capacity_liters_per_day;
            $billedL = (int) intdiv($inputL * 78, 100); // 78% konsumsi resmi ditagih
            $unbilledL = (int) intdiv($inputL * 4, 100); // 4% pemadam kebakaran & flushing
            $apparentLossesL = (int) intdiv($inputL * 3, 100); // 3% kesalahan meter
            $realLossesL = $inputL - ($billedL + $unbilledL + $apparentLossesL); // 15% fisik pipa

            $result = $this->waterBalance->compute(
                systemInputVolume: $inputL,
                billedAuthorizedConsumption: $billedL,
                unbilledAuthorizedConsumption: $unbilledL,
                unauthorizedConsumption: 0,
                customerMeteringInaccuracies: $apparentLossesL,
                systematicDataHandlingErrors: 0,
                leakageOnMains: (int) intdiv($realLossesL * 60, 100),
                leakageAndOverflowsStorage: (int) intdiv($realLossesL * 10, 100),
                leakageOnServiceConnections: (int) intdiv($realLossesL * 30, 100),
            );

            $reports[] = new IwaWaterAuditReportDTO(
                networkId: (int) $net->id,
                networkName: (string) $net->network_name,
                zoneCode: (string) $net->zone_code,
                systemInputVolumeL: $result->systemInputVolume,
                billedAuthorizedL: $result->billedAuthorizedConsumption,
                unbilledAuthorizedL: $result->unbilledAuthorizedConsumption,
                apparentLossesL: $result->apparentLosses,
                realLossesL: $result->realLosses,
                nonRevenueWaterL: $result->nonRevenueWater,
                nrwPercentageBps: $result->nrwPercentageBps,
                status: (string) $net->status,
                verificationHash: $result->certificateHash,
            );
        }

        return $reports;
    }

    /**
     * Hasilkan laporan komprehensif performa pembangkit pendingin distrik (District Cooling).
     *
     * @return array<DistrictCoolingPerformanceReportDTO>
     */
    public function generateCoolingPerformanceReports(): array
    {
        $plants = DistrictCoolingPlant::with('chillers')->get();
        $reports = [];

        foreach ($plants as $plant) {
            $totalCapacityRt = (int) $plant->cooling_capacity_rt;
            $chillersCount = $plant->chillers->count();
            $activeCount = $plant->chillers->where('status', 'running')->count();

            // Simulasi agregasi operasional harian 24 jam beban rata-rata 75%
            $coolingDeliveredRth = (int) intdiv($totalCapacityRt * 18, 1);
            $totalElectricityKwh = (int) intdiv($coolingDeliveredRth * 65, 100); // 0.65 kW/TR-h

            $copResult = $this->chillerCopAnalyzer->compute(
                coolingTons: max(1, $totalCapacityRt),
                electricalPowerKw: max(1, (int) intdiv($totalCapacityRt * 65, 100)),
                designKwPerTonBps: 6500,
                chilledWaterFlowLps: max(1, (int) intdiv($totalCapacityRt * 7, 100)),
                deltaTempTenthCelsius: 55
            );

            $reports[] = new DistrictCoolingPerformanceReportDTO(
                plantId: (int) $plant->id,
                plantName: (string) $plant->plant_name,
                plantCode: (string) $plant->plant_code,
                totalInstalledCapacityRt: $totalCapacityRt,
                totalChillersCount: $chillersCount,
                activeChillersCount: $activeCount,
                totalCoolingDeliveredRth: $coolingDeliveredRth,
                totalElectricityConsumedKwh: $totalElectricityKwh,
                averageKwPerTonBps: $copResult->actualKwPerTonBps,
                averageCopBps: $copResult->copBps,
                overallStatus: $copResult->operationalStatus,
                verificationHash: $copResult->certificateHash,
            );
        }

        return $reports;
    }

    /**
     * Hasilkan laporan produksi Biogas Waste-to-Energy dan dekarbonisasi.
     *
     * @return array<BiogasWteProductionReportDTO>
     */
    public function generateBiogasWteReports(): array
    {
        $facilities = WasteTreatmentFacility::with('intakes')->get();
        $reports = [];

        foreach ($facilities as $fac) {
            $totalReceivedKg = (int) $fac->intakes->sum('tonnage_kg');
            $intakeCount = $fac->intakes->count();
            $totalRevenueIdr = (int) $fac->intakes->sum('total_tipping_fee_idr');

            $calcKg = max(1000, $totalReceivedKg);
            $biogasResult = $this->biogasCalculator->compute(
                wasteTonnageKg: $calcKg,
                totalSolidsBps: 2000,
                volatileSolidsBps: 8000,
                biodegradabilityBps: 7000,
                biogasYieldLitersPerKgVs: (int) $fac->biogas_yield_m3_per_ton * 10,
                methaneContentBps: 6000,
                electricalEfficiencyBps: 3800
            );

            $reports[] = new BiogasWteProductionReportDTO(
                facilityId: (int) $fac->id,
                facilityName: (string) $fac->facility_name,
                facilityCode: (string) $fac->facility_code,
                totalWasteReceivedKg: $totalReceivedKg,
                totalBiogasProducedM3: $biogasResult->biogasYieldM3,
                totalElectricityGeneratedKwh: $biogasResult->electricGrossKwh,
                totalTippingFeeRevenueIdr: $totalRevenueIdr,
                totalAvoidedEmissionsCo2eKg: $biogasResult->avoidedEmissionCo2eKg,
                intakeBatchesCount: $intakeCount,
                status: (string) $fac->status,
                verificationHash: $biogasResult->certificateHash,
            );
        }

        return $reports;
    }

    /**
     * Format data laporan ke format CSV berstandar audit.
     *
     * @param  array<string>  $headers
     * @param  array<array<mixed>>  $rows
     */
    public function formatAsCsv(array $headers, array $rows): string
    {
        $fp = fopen('php://temp', 'r+');
        if ($fp === false) {
            return '';
        }

        fputcsv($fp, $headers);
        foreach ($rows as $row) {
            fputcsv($fp, $row);
        }

        rewind($fp);
        $csv = stream_get_contents($fp);
        fclose($fp);

        return $csv !== false ? $csv : '';
    }
}
