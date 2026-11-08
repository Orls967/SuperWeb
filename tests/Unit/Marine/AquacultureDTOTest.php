<?php

declare(strict_types=1);

namespace Tests\Unit\Marine;

use Modules\Marine\Application\DTOs\AquacultureBatchStockingDTO;
use Modules\Marine\Application\DTOs\EscrowSettlementConditionDTO;
use Modules\Marine\Application\DTOs\EudrComplianceDeclarationDTO;
use Modules\Marine\Application\DTOs\ReeferColdChainTelemetryDTO;
use Modules\Marine\Application\DTOs\WaterQualityReadingDTO;
use Tests\TestCase;

class AquacultureDTOTest extends TestCase
{
    public function test_batch_stocking_dto_calculations(): void
    {
        $dto = new AquacultureBatchStockingDTO(
            hatcheryId: 1,
            pondId: 1,
            speciesScientificName: 'Litopenaeus vannamei',
            commercialName: 'Whiteleg Shrimp',
            initialStockCount: 150000,
            pondAreaHectares: 0.5,
            averageBodyWeightGrams: 0.002,
            stockingDate: '2026-08-01',
            expectedHarvestDate: '2026-11-01',
            targetHarvestWeightGrams: 25.0
        );

        $this->assertEquals(30.0, $dto->calculateDensityPerSquareMeter());
        $this->assertEquals(3187.5, $dto->calculateTargetBiomassKg());
    }

    public function test_water_quality_reading_dto_grading(): void
    {
        $optimalDto = new WaterQualityReadingDTO(
            batchId: 1,
            sensorDeviceUuid: 'SENSOR-01',
            dissolvedOxygenMgL: 5.8,
            phLevel: 7.8,
            waterTemperatureCelsius: 29.5,
            salinityPpt: 22.0,
            ammoniaNitrogenPpm: 0.05,
            nitriteNo2Ppm: 0.01,
            alkalinityMgL: 140.0,
            orpMv: 240.0
        );

        $this->assertEquals('OPTIMAL', $optimalDto->determineWaterQualityGrade());
        $this->assertFalse($optimalDto->requiresImmediateAerationBoost());

        $criticalDto = new WaterQualityReadingDTO(
            batchId: 1,
            sensorDeviceUuid: 'SENSOR-01',
            dissolvedOxygenMgL: 1.5,
            phLevel: 7.8,
            waterTemperatureCelsius: 29.5,
            salinityPpt: 22.0,
            ammoniaNitrogenPpm: 0.60,
            nitriteNo2Ppm: 0.01,
            alkalinityMgL: 140.0,
            orpMv: 240.0
        );

        $this->assertEquals('CRITICAL_ANOXIA', $criticalDto->determineWaterQualityGrade());
        $this->assertTrue($criticalDto->requiresImmediateAerationBoost());
    }

    public function test_reefer_cold_chain_dto_breach_detection(): void
    {
        $normal = new ReeferColdChainTelemetryDTO(
            exportShipmentId: 1,
            recordedAt: '2026-11-08 12:00:00',
            ambientTemperatureCelsius: 28.0,
            supplyAirTemperatureCelsius: -22.5,
            returnAirTemperatureCelsius: -21.8,
            cargoProbeTemperatureCelsius: -22.0,
            humidityPercentage: 85.0,
            latitude: -7.2,
            longitude: 112.7
        );
        $this->assertFalse($normal->isTemperatureBreached());

        $breach = new ReeferColdChainTelemetryDTO(
            exportShipmentId: 1,
            recordedAt: '2026-11-08 14:00:00',
            ambientTemperatureCelsius: 32.0,
            supplyAirTemperatureCelsius: -14.0,
            returnAirTemperatureCelsius: -13.5,
            cargoProbeTemperatureCelsius: -13.8, // > -15.0 C breach
            humidityPercentage: 88.0,
            latitude: -7.2,
            longitude: 112.7
        );
        $this->assertTrue($breach->isTemperatureBreached());
    }

    public function test_escrow_settlement_condition_payout_calculation(): void
    {
        $escrow = new EscrowSettlementConditionDTO(
            settlementCode: 'ESC-001',
            farmerId: 1,
            grossHarvestValueMinorCents: 2500000,
            feedDeductionMinorCents: 450000,
            hatcherySeedDeductionMinorCents: 150000,
            qualityGradePremiumMinorCents: 100000,
            spsAssayPassed: true,
            massBalanceVerified: true,
            vesselBillOfLadingIssued: true
        );

        $this->assertEquals(2000000, $escrow->calculateNetFarmerPayoutMinorCents());
        $this->assertTrue($escrow->isFullyEligibleForRelease());
    }

    public function test_eudr_compliance_declaration(): void
    {
        $eudr = new EudrComplianceDeclarationDTO(
            statementNumber: 'DDS-001',
            batchId: 1,
            centerLatitude: 3.32,
            centerLongitude: 117.59,
            geolocationPolygonGeojson: ['type' => 'Polygon'],
            establishmentDate: '2018-05-10',
            zeroDeforestationVerified: true,
            indigenousRightsRespected: true
        );

        $this->assertTrue($eudr->isCompliantWith2020Cutoff());
        $this->assertEquals('EUDR_COMPLIANT', $eudr->getSummaryVerdict());
    }
}
