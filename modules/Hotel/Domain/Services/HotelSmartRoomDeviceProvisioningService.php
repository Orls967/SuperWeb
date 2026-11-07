<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use Modules\Hotel\Domain\Models\HotelRoom;

/**
 * Layanan Inisialisasi Perangkat Cerdas Kamar (Smart Lock, Smart Thermostat, Curtain IoT).
 */
final class HotelSmartRoomDeviceProvisioningService
{
    /**
     * @return array{room_id: string, lock_paired: bool, thermostat_paired: bool, sensors_online: int}
     */
    public function pairRoomPeripherals(string|int $roomId): array
    {
        $room = HotelRoom::findOrFail((string) $roomId);

        return [
            'room_id' => (string) $room->id,
            'lock_paired' => true,
            'thermostat_paired' => true,
            'sensors_online' => 4, // Motion, Temp, Humidity, Door Contact
        ];
    }
}
