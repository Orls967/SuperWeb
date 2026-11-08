<?php

declare(strict_types=1);

namespace Modules\Marine\Application\Services;

use DomainException;
use Modules\Marine\Domain\Algorithms\AquacultureMassBalanceVerifier;
use Modules\Marine\Domain\Models\AquacultureBatch;
use Modules\Marine\Domain\Models\AquacultureExportLot;
use Modules\Marine\Domain\Models\AquacultureHarvestOperation;
use Modules\Marine\Domain\Models\ColdChainTelemetryLog;
use Modules\Marine\Domain\Models\ExportReeferShipment;
use Modules\Marine\Domain\Models\ProcessingPackaging;
use Modules\Marine\Domain\StateMachines\AquacultureBatchStateMachine;

/**
 * Service operasi panen, pemrosesan pabrik (processing plant), dan rantai dingin (cold chain) (Fase 171).
 */
class HarvestProcessingColdChainService
{
    protected AquacultureMassBalanceVerifier $massBalanceVerifier;

    protected AquacultureBatchStateMachine $batchStateMachine;

    public function __construct(
        AquacultureMassBalanceVerifier $massBalanceVerifier,
        AquacultureBatchStateMachine $batchStateMachine
    ) {
        $this->massBalanceVerifier = $massBalanceVerifier;
        $this->batchStateMachine = $batchStateMachine;
    }

    /**
     * Catat operasi panen tambak.
     *
     * @param  array<string, mixed>  $harvestData
     */
    public function recordHarvest(int $batchId, array $harvestData): AquacultureHarvestOperation
    {
        $batch = AquacultureBatch::findOrFail($batchId);

        $harvestBiomass = (float) ($harvestData['total_biomass_harvested_kg'] ?? 0.0);
        if ($harvestBiomass <= 0.0) {
            throw new DomainException('Biomassa hasil panen harus lebih besar dari 0 kg.');
        }

        $harvestCode = 'HRV-'.strtoupper(substr(hash('sha256', $batch->batch_code.microtime()), 0, 10));

        $harvest = AquacultureHarvestOperation::create([
            'batch_id' => $batch->id,
            'harvest_code' => $harvestCode,
            'harvest_date' => (string) ($harvestData['harvest_date'] ?? now()->toDateString()),
            'harvest_type' => (string) ($harvestData['harvest_type'] ?? 'TOTAL_HARVEST'),
            'total_biomass_harvested_kg' => $harvestBiomass,
            'mean_weight_grams' => (float) ($harvestData['mean_weight_grams'] ?? 25.0),
            'shrimp_size_count_per_kg' => (int) ($harvestData['shrimp_size_count_per_kg'] ?? 40),
            'chilled_ice_slurry_temp_celsius' => (float) ($harvestData['chilled_ice_slurry_temp_celsius'] ?? 1.5),
            'target_processing_plant' => (string) ($harvestData['target_processing_plant'] ?? 'PT Samudera Cold Processing'),
            'harvest_supervisor' => (string) ($harvestData['harvest_supervisor'] ?? 'Chief Field Inspector'),
            'status' => 'COMPLETED',
        ]);

        if (($harvestData['harvest_type'] ?? 'TOTAL_HARVEST') === 'TOTAL_HARVEST') {
            $batch->update([
                'status' => AquacultureBatchStateMachine::STATUS_HARVESTED,
                'current_population' => 0,
                'current_estimated_biomass_kg' => 0.0,
            ]);
        }

        return $harvest;
    }

    /**
     * Buat lot ekspor dan proses produk olahan dengan verifikasi neraca massa (mass balance).
     *
     * @param  array<string, mixed>  $lotData
     * @param  array<string, mixed>  $packagingData
     */
    public function processAndPackExportLot(
        int $harvestOperationId,
        array $lotData,
        array $packagingData
    ): AquacultureExportLot {
        $harvest = AquacultureHarvestOperation::findOrFail($harvestOperationId);

        $processedWeight = (float) ($packagingData['net_weight_kg'] ?? 0.0);
        $productForm = (string) ($packagingData['product_form'] ?? 'HLSO');
        $glazePct = (float) ($packagingData['glaze_percentage'] ?? 8.0);

        // Verifikasi Mass Balance
        $massBalance = $this->massBalanceVerifier->verifyMassBalance(
            $harvest->total_biomass_harvested_kg,
            $processedWeight,
            $productForm,
            $glazePct
        );

        if (! $massBalance['is_compliant']) {
            throw new DomainException("Pelanggaran Neraca Massa Ekspor (ASC/MSC): {$massBalance['audit_message']}");
        }

        $lotNumber = 'LOT-EXP-'.strtoupper(substr(hash('sha256', $harvest->harvest_code.microtime()), 0, 10));

        $exportLot = AquacultureExportLot::create([
            'harvest_operation_id' => $harvest->id,
            'lot_number' => $lotNumber,
            'species' => $harvest->batch->species,
            'product_form' => $productForm,
            'net_weight_kg' => $processedWeight,
            'carton_count' => (int) ($packagingData['carton_count'] ?? 100),
            'storage_temperature_celsius' => (float) ($packagingData['storage_temperature_celsius'] ?? -20.0),
            'quality_grade' => 'PENDING_ASSAY',
            'is_asc_certified' => (bool) ($lotData['is_asc_certified'] ?? true),
            'is_msc_certified' => (bool) ($lotData['is_msc_certified'] ?? false),
            'is_bap_certified' => (bool) ($lotData['is_bap_certified'] ?? true),
            'haccp_approval_number' => (string) ($lotData['haccp_approval_number'] ?? 'ID-HACCP-SMD-0881'),
            'status' => 'PACKED_IN_STORAGE',
        ]);

        ProcessingPackaging::create([
            'export_lot_id' => $exportLot->id,
            'pack_code' => 'PACK-'.$lotNumber,
            'product_form' => $productForm,
            'freezing_method' => (string) ($packagingData['freezing_method'] ?? 'IQF_BLAST_FREEZER'),
            'glaze_percentage' => $glazePct,
            'net_weight_kg' => $processedWeight,
            'cartons_packed' => (int) ($packagingData['carton_count'] ?? 100),
            'packaging_date' => now()->toDateString(),
            'best_before_date' => now()->addMonths(24)->toDateString(),
            'storage_facility_code' => (string) ($packagingData['storage_facility_code'] ?? 'COLD-HUB-01'),
            'mass_balance_yield_ratio' => $massBalance['actual_yield_percentage'],
        ]);

        return $exportLot;
    }

    /**
     * Catat log sensor suhu reefer container (Cold Chain Monitoring).
     */
    public function recordColdChainTelemetry(
        int $reeferShipmentId,
        float $temperatureCelsius,
        float $humidityPercentage,
        string $gpsLatitude,
        string $gpsLongitude,
        bool $powerSupplyActive = true
    ): ColdChainTelemetryLog {
        $shipment = ExportReeferShipment::findOrFail($reeferShipmentId);

        $isBreached = ($temperatureCelsius > -15.0); // Ambang batas dingin seafood beku: -15°C
        $notes = $isBreached ? 'COLD_CHAIN_TEMPERATURE_BREACH_ALERT' : 'NORMAL_TEMPERATURE';

        if ($isBreached && ! $shipment->is_cold_chain_breached) {
            $shipment->update([
                'is_cold_chain_breached' => true,
                'current_temperature_celsius' => $temperatureCelsius,
            ]);
        } else {
            $shipment->update([
                'current_temperature_celsius' => $temperatureCelsius,
            ]);
        }

        return ColdChainTelemetryLog::create([
            'export_shipment_id' => $shipment->id,
            'recorded_at' => now()->toDateTimeString(),
            'ambient_temperature_celsius' => 28.0,
            'supply_air_temperature_celsius' => $temperatureCelsius - 0.5,
            'return_air_temperature_celsius' => $temperatureCelsius + 0.5,
            'cargo_probe_temperature_celsius' => $temperatureCelsius,
            'humidity_percentage' => $humidityPercentage,
            'latitude' => (float) $gpsLatitude,
            'longitude' => (float) $gpsLongitude,
            'compressor_running' => $powerSupplyActive,
            'defrost_cycle_active' => false,
            'temperature_breach_detected' => $isBreached,
        ]);
    }
}
