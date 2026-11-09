<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Services;

use DomainException;
use Illuminate\Support\Str;
use Modules\Hospital\Application\DTOs\ColdChainLogDTO;
use Modules\Hospital\Domain\Models\ColdChainTelemetry;
use Modules\Hospital\Domain\Models\MedFridgeBreach;
use Modules\Hospital\Domain\Models\PharmacyBranchLot;
use Modules\Hospital\Domain\StateMachines\ColdChainStateMachine;

/**
 * Layanan Domain Pemantauan Rantai Dingin Farmasi & Vaksin (Cold-Chain Surveillance).
 * Memantau telemetri suhu kontinu lemari pendingin farmasi (Chiller 2-8°C, Freezer -20°C, Ultra-Low -80°C).
 * Secara otomatis mendeteksi excursion, mengunci batch obat ke status QUARANTINED,
 * dan membekukan utang supplier farmasi jika barang tiba dalam kondisi rusak suhu.
 */
class ColdChainMonitoringService
{
    /**
     * Catat data telemetri suhu dan evaluasi alarm ekskursi.
     */
    public function recordAndEvaluateTelemetry(
        string $sensorDeviceCode,
        string $fridgeUnitCode,
        string $storageZone,
        float $temperatureC,
        ?float $humidityPct = null,
        ?string $recordedAt = null
    ): ColdChainLogDTO {
        $timestamp = $recordedAt ? date('Y-m-d H:i:s', strtotime($recordedAt)) : now()->toDateTimeString();

        $eval = ColdChainTelemetry::evaluateExcursion($storageZone, $temperatureC);
        $isExcursion = (bool) $eval['is_excursion'];
        $severity = (string) $eval['severity'];

        $telemetry = ColdChainTelemetry::create([
            'sensor_device_code' => $sensorDeviceCode,
            'fridge_unit_code' => $fridgeUnitCode,
            'storage_zone' => $storageZone,
            'temperature_c' => $temperatureC,
            'humidity_pct' => $humidityPct,
            'is_excursion' => $isExcursion,
            'excursion_severity' => $severity,
            'recorded_at' => $timestamp,
        ]);

        $requiresQuarantine = $isExcursion && in_array($severity, ['WARNING', 'CRITICAL'], true);

        // Jika terdeteksi penyimpangan suhu kritis, buat catatan insiden breach & karantina batch aktif
        if ($requiresQuarantine) {
            $this->triggerFridgeBreachQuarantine($fridgeUnitCode, $temperatureC, $severity);
        }

        return new ColdChainLogDTO(
            sensorDeviceCode: $sensorDeviceCode,
            fridgeUnitCode: $fridgeUnitCode,
            storageZone: $storageZone,
            temperatureC: $temperatureC,
            humidityPct: $humidityPct,
            isExcursion: $isExcursion,
            severity: $severity,
            recordedAtIso: $telemetry->recorded_at->toIso8601String(),
            requiresImmediateQuarantine: $requiresQuarantine,
        );
    }

    /**
     * Memicu kuncian karantina darurat untuk semua lot obat di dalam unit pendingin yang bermasalah.
     */
    public function triggerFridgeBreachQuarantine(string $fridgeUnitCode, float $recordedTempC, string $severity): MedFridgeBreach
    {
        $breachCode = 'BREACH-'.date('Ymd').'-'.strtoupper(Str::random(6));

        // Buat record insiden pelanggaran rantai dingin
        $breach = MedFridgeBreach::create([
            'breach_code' => $breachCode,
            'fridge_unit_code' => $fridgeUnitCode,
            'lot_number' => "LOT-AUTO-{$fridgeUnitCode}",
            'recorded_temp_c' => $recordedTempC,
            'supplier_id' => 1,
            'held_supplier_payable_idr' => 25_000_000,
            'status' => ColdChainStateMachine::STATUS_QUARANTINED,
        ]);

        // Karantina semua stok lot farmasi yang berada di unit lemari pendingin tersebut
        PharmacyBranchLot::where('fridge_unit_code', $fridgeUnitCode)
            ->update([
                'is_quarantined' => true,
            ]);

        return $breach;
    }

    /**
     * Membebaskan lot farmasi dari karantina setelah lulus uji validasi stabilitas termal klinis.
     */
    public function releaseFromQuarantine(MedFridgeBreach $breach, string $stabilityProtocolReference): MedFridgeBreach
    {
        if (empty(trim($stabilityProtocolReference))) {
            throw new DomainException('Pelepasan lot farmasi membutuhkan nomor referensi protokol pengujian stabilitas klinis.');
        }

        ColdChainStateMachine::transition(
            $breach,
            ColdChainStateMachine::STATUS_RELEASED,
            "Lulus pengujian stabilitas: {$stabilityProtocolReference}"
        );

        PharmacyBranchLot::where('fridge_unit_code', $breach->fridge_unit_code)
            ->update([
                'is_quarantined' => false,
            ]);

        return $breach;
    }
}
