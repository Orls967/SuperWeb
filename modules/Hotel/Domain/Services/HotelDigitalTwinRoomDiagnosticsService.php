<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use Modules\Hotel\Domain\Models\HotelRoomEnergyLog;

/**
 * Layanan Diagnostik Sensor Suhu, Kelembapan, & Konsumsi Listrik Kamar.
 */
final class HotelDigitalTwinRoomDiagnosticsService
{
    /**
     * @return array{room_id: string, health_status: string, avg_temp_celsius: float, anomaly_detected: bool}
     */
    public function diagnoseRoomSensors(string|int $roomId): array
    {
        $logs = HotelRoomEnergyLog::where('room_id', (string) $roomId)
            ->latest()
            ->take(10)
            ->get();

        if ($logs->isEmpty()) {
            return [
                'room_id' => (string) $roomId,
                'health_status' => 'NORMAL',
                'avg_temp_celsius' => 24.0,
                'anomaly_detected' => false,
            ];
        }

        $avgTemp = round((float) $logs->avg('ambient_temp_celsius'), 1);
        $anomaly = $avgTemp > 30.0 || $avgTemp < 18.0;

        return [
            'room_id' => (string) $roomId,
            'health_status' => $anomaly ? 'ATTENTION_REQUIRED' : 'HEALTHY',
            'avg_temp_celsius' => $avgTemp,
            'anomaly_detected' => $anomaly,
        ];
    }
}
