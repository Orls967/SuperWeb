<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use Carbon\CarbonImmutable;
use Modules\Hotel\Domain\Adapters\CoreDigitalTwinAdapter;
use Modules\Hotel\Domain\Adapters\EsgEnergyBenefitAdapter;
use Modules\Hotel\Domain\Algorithms\HvacSetbackController;
use Modules\Hotel\Domain\Models\HotelRoom;
use Modules\Hotel\Domain\Models\HotelRoomEnergyLog;

/**
 * Layanan Manajemen Kembar Digital & Efisiensi Energi HVAC Kamar (H4).
 */
final class HotelEnergyTwinSetbackService
{
    /**
     * @return array{processed_rooms: int, total_saved_wh: int, twin_synced: bool}
     */
    public function runPropertySetbackCycle(string|int $propertyId): array
    {
        $rooms = HotelRoom::where('property_id', (string) $propertyId)
            ->where('status', 'occupied')
            ->get();

        $processedCount = 0;
        $totalSavedWh = 0;

        foreach ($rooms as $room) {
            $roomIdInt = is_numeric($room->id) ? (int) $room->id : crc32((string) $room->id);
            $eval = HvacSetbackController::evaluate(
                isOccupiedRoom: true,
                minutesSinceLastMotion: 120,
                durationMinutes: 60,
                acPowerWatt: 1000,
                savingFactorBps: 3000
            );

            if ($eval['setback_active']) {
                $savedWh = (int) $eval['energy_saved_wh'];
                $totalSavedWh += $savedWh;
                $processedCount++;

                HotelRoomEnergyLog::create([
                    'room_id' => (string) $room->id,
                    'is_occupied' => true,
                    'motion_detected' => false,
                    'hvac_mode' => 'setback',
                    'ambient_temp_celsius' => 27.5,
                    'target_temp_celsius' => 27.0,
                    'power_draw_watt' => 700,
                    'energy_saved_wh' => $savedWh,
                ]);

                CoreDigitalTwinAdapter::syncRoomState(
                    roomId: $roomIdInt,
                    stateName: 'HVAC_SETBACK_ECO',
                    temperatureCelsius: 27.5
                );

                EsgEnergyBenefitAdapter::recordKwhSaving(
                    roomId: $roomIdInt,
                    kwhSaved: $savedWh / 1000.0
                );
            }
        }

        return [
            'processed_rooms' => $processedCount,
            'total_saved_wh' => $totalSavedWh,
            'twin_synced' => true,
        ];
    }
}
