<?php

declare(strict_types=1);

namespace Modules\Marine\Application\Services;

use DomainException;
use Modules\Marine\Domain\Algorithms\FeedConversionRatioOptimizer;
use Modules\Marine\Domain\Models\AquacultureBatch;
use Modules\Marine\Domain\Models\FeedingEvent;
use Modules\Marine\Domain\Models\FeedInventoryBatch;
use Modules\Marine\Domain\Models\WaterQualityTelemetry;

/**
 * Service telemetri kualitas air dan manajemen pemberian pakan tambak (Fase 171).
 */
class FeedAndWaterQualityTelemetryService
{
    protected FeedConversionRatioOptimizer $fcrOptimizer;

    public function __construct(FeedConversionRatioOptimizer $fcrOptimizer)
    {
        $this->fcrOptimizer = $fcrOptimizer;
    }

    /**
     * Catat telemetri sensor kualitas air tambak.
     *
     * @param  array<string, mixed>  $telemetryData
     */
    public function recordWaterTelemetry(int $batchId, array $telemetryData): WaterQualityTelemetry
    {
        $batch = AquacultureBatch::findOrFail($batchId);

        $do = (float) ($telemetryData['dissolved_oxygen_mg_l'] ?? 5.5);
        $temp = (float) ($telemetryData['water_temperature_celsius'] ?? 29.5);
        $ph = (float) ($telemetryData['ph_level'] ?? 7.8);
        $salinity = (float) ($telemetryData['salinity_ppt'] ?? 25.0);
        $orp = (float) ($telemetryData['orp_mv'] ?? 220.0);
        $ammonia = (float) ($telemetryData['total_ammonia_nitrogen_ppm'] ?? 0.05);

        $isCritical = ($do < 3.0 || $ph < 7.0 || $ph > 9.0 || $ammonia > 0.5);
        $alertMessage = null;

        if ($do < 2.0) {
            $alertMessage = 'ALERT_ANOMALOUS_ANOXIA: Kadar oksigen terlarut kritis!';
        } elseif ($ammonia > 0.5) {
            $alertMessage = 'ALERT_AMMONIA_SPIKE: Amonia bebas melebihi ambang batas aman!';
        }

        return WaterQualityTelemetry::create([
            'batch_id' => $batch->id,
            'recorded_at' => (string) ($telemetryData['recorded_at'] ?? now()->toDateTimeString()),
            'dissolved_oxygen_mg_l' => $do,
            'water_temperature_celsius' => $temp,
            'ph_level' => $ph,
            'salinity_ppt' => $salinity,
            'oxidation_reduction_potential_mv' => $orp,
            'total_ammonia_nitrogen_ppm' => $ammonia,
            'nitrite_no2_ppm' => (float) ($telemetryData['nitrite_no2_ppm'] ?? 0.015),
            'alkalinity_mg_l' => (float) ($telemetryData['alkalinity_mg_l'] ?? 140.0),
            'sensor_device_uuid' => (string) ($telemetryData['sensor_device_id'] ?? 'IOT-POND-SENSOR-01'),
            'aerator_active' => true,
            'water_quality_grade' => $isCritical ? 'CRITICAL_ANOXIA' : 'OPTIMAL',
        ]);
    }

    /**
     * Catat peristiwa pemberian pakan dan hitung rekomendasi pakan harian.
     *
     * @param  array<string, mixed>  $feedingData
     */
    public function recordFeedingEvent(int $batchId, int $feedInventoryId, array $feedingData): FeedingEvent
    {
        $batch = AquacultureBatch::findOrFail($batchId);
        $inventory = FeedInventoryBatch::findOrFail($feedInventoryId);

        $feedKg = (float) ($feedingData['feed_amount_kg'] ?? 50.0);
        if ($feedKg <= 0.0) {
            throw new DomainException('Jumlah pakan harus lebih besar dari 0 kg.');
        }

        if ($inventory->remaining_stock_kg < $feedKg) {
            throw new DomainException("Stok pakan tidak mencukupi (Tersedia: {$inventory->remaining_stock_kg} kg, Diminta: {$feedKg} kg).");
        }

        $latestTelemetry = WaterQualityTelemetry::where('batch_id', $batch->id)->latest('recorded_at')->first();
        $do = $latestTelemetry ? (float) $latestTelemetry->dissolved_oxygen_mg_l : 5.0;
        $temp = $latestTelemetry ? (float) $latestTelemetry->water_temperature_celsius : 29.0;
        $ph = $latestTelemetry ? (float) $latestTelemetry->ph_level : 7.8;
        $trayLeftoverRate = (float) ($feedingData['tray_leftover_fraction'] ?? 0.05);

        $adjustment = $this->fcrOptimizer->calculateDailyRationAdjustment(
            $feedKg,
            $do,
            $temp,
            $ph,
            1.0 - $trayLeftoverRate
        );

        $inventory->decrement('remaining_stock_kg', $feedKg);

        return FeedingEvent::create([
            'batch_id' => $batch->id,
            'feed_inventory_id' => $inventory->id,
            'feeding_time' => (string) ($feedingData['feeding_time'] ?? now()->toDateTimeString()),
            'feed_amount_kg' => $feedKg,
            'adjusted_recommended_kg' => $adjustment['adjusted_daily_feed_kg'],
            'tray_leftover_percentage' => round($trayLeftoverRate * 100.0, 2),
            'dissolved_oxygen_at_feeding' => $do,
            'water_temp_at_feeding' => $temp,
            'operator_name' => (string) ($feedingData['operator_name'] ?? 'Pond Technician'),
            'notes' => $adjustment['feeding_recommendation'],
        ]);
    }

    /**
     * Evaluasi efisiensi kumulatif FCR tambak saat ini.
     *
     * @return array{
     *     total_feed_used_kg: float,
     *     current_biomass_gain_kg: float,
     *     calculated_fcr: float,
     *     efficiency_rating: string
     * }
     */
    public function evaluateBatchFcr(int $batchId): array
    {
        $batch = AquacultureBatch::findOrFail($batchId);
        $totalFeedUsed = (float) FeedingEvent::where('batch_id', $batch->id)->sum('feed_amount_kg');

        $initialBiomass = (float) ($batch->stocking_count * 0.002); // 2 gram rata-rata awal
        $currentBiomass = (float) $batch->current_estimated_biomass_kg;

        if ($currentBiomass <= $initialBiomass) {
            $currentBiomass = $initialBiomass + 10.0;
        }

        $fcrResult = $this->fcrOptimizer->calculateFcr(
            $totalFeedUsed,
            $initialBiomass,
            $currentBiomass,
            0.0
        );

        return [
            'total_feed_used_kg' => round($totalFeedUsed, 2),
            'current_biomass_gain_kg' => $fcrResult['biomass_gain_kg'],
            'calculated_fcr' => $fcrResult['economic_fcr'],
            'efficiency_rating' => $fcrResult['efficiency_rating'],
        ];
    }
}
