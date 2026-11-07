<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Actions;

use Carbon\CarbonImmutable;
use Modules\Hotel\Domain\Adapters\CoreDigitalTwinAdapter;
use Modules\Hotel\Domain\Adapters\EsgEnergyBenefitAdapter;
use Modules\Hotel\Domain\Algorithms\HvacSetbackController;
use Modules\Hotel\Domain\Models\HotelRoom;
use Modules\Hotel\Domain\Models\HotelRoomEnergyLog;

/**
 * Pemindaian Berkala Sensor Kamar & Pengendalian Setback Hemat Energi H4 (D91-5)
 */
final class RunSetbackScanAction
{
    /**
     * @return array{scanned_rooms: int, setback_rooms: int, total_saved_wh: int}
     */
    public function execute(int $propertyId, ?string $targetDate = null): array
    {
        $date = $targetDate ?? CarbonImmutable::now()->format('Y-m-d');
        $rooms = HotelRoom::where('property_id', $propertyId)->get();

        $scannedCount = 0;
        $setbackCount = 0;
        $totalWh = 0;

        foreach ($rooms as $room) {
            $isOccupied = $room->status === 'occupied';
            // Simulasi sensor gerak: jika occupied tanpa gerak 3 jam (180 menit)
            $minutesMotion = $isOccupied ? 180 : 0;
            $durationMinutes = 180;

            $eval = HvacSetbackController::evaluate(
                isOccupiedRoom: $isOccupied,
                minutesSinceLastMotion: $minutesMotion,
                durationMinutes: $durationMinutes,
                acPowerWatt: 1200,
                savingFactorBps: 3000
            );

            $scannedCount++;
            if ($eval['setback_active']) {
                $setbackCount++;
                $totalWh += $eval['energy_saved_wh'];

                // Catat Log Energi Kamar
                HotelRoomEnergyLog::create([
                    'property_id' => $propertyId,
                    'room_id' => $room->id,
                    'log_date' => $date,
                    'event_time' => CarbonImmutable::now(),
                    'motion_detected' => false,
                    'setback_active' => true,
                    'ac_power_watt' => 1200,
                    'setback_minutes' => $eval['setback_minutes'],
                    'energy_saved_wh' => $eval['energy_saved_wh'],
                    'esg_reported' => true,
                ]);

                // Sinkron ke Core Digital Twin
                CoreDigitalTwinAdapter::syncRoomTwin($room->id, [
                    'temperature_delta' => $eval['setback_temp_delta_c'],
                    'setback_active' => true,
                    'energy_saved_wh' => $eval['energy_saved_wh'],
                ]);

                // Laporkan Ke ESG Emission Benefit
                EsgEnergyBenefitAdapter::recordRoomSetbackBenefit($propertyId, $room->id, $eval['energy_saved_wh']);
            }
        }

        return [
            'scanned_rooms' => $scannedCount,
            'setback_rooms' => $setbackCount,
            'total_saved_wh' => $totalWh,
        ];
    }
}
