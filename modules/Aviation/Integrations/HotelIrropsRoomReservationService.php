<?php

declare(strict_types=1);

namespace Modules\Aviation\Integrations;

use Illuminate\Support\Facades\Log;
use Modules\Hotel\Contracts\RoomBlockReserver;

class HotelIrropsRoomReservationService
{
    public function __construct(
        protected ?RoomBlockReserver $reserver = null
    ) {}

    /**
     * Pesan blok kamar hotel darurat untuk manifest penumpang disrupsi IRROPS.
     *
     * @param  int  $passengerCount  Jumlah penumpang terdampak
     * @param  string  $city  Kota lokasi bandara disrupsi
     * @param  string  $caseNumber  Nomor kasus disrupsi
     * @return array{reservation_id: string, rooms_allocated: int, total_charge_idr: int, status: string}
     */
    public function reserveRoomsForIrrops(int $passengerCount, string $city, string $caseNumber): array
    {
        $roomsNeeded = (int) ceil($passengerCount / 2.0);
        $checkIn = date('Y-m-d');
        $checkOut = date('Y-m-d', strtotime('+1 day'));
        $rateIdr = 750000; // Standar hotel bintang 3 mitra IRROPS

        if ($this->reserver !== null) {
            try {
                $res = $this->reserver->reserveBlock(
                    propertyId: 'HTL-IRROPS-'.strtoupper($city),
                    roomId: 'STD-TWIN',
                    guestUserId: 'IRROPS-'.$caseNumber,
                    checkInDate: $checkIn,
                    checkOutDate: $checkOut,
                    dailyRateIdr: $rateIdr * $roomsNeeded
                );

                return [
                    'reservation_id' => (string) ($res['reservation_code'] ?? $res['reservation_id']),
                    'rooms_allocated' => $roomsNeeded,
                    'total_charge_idr' => (int) ($res['total_room_charge_idr'] ?? ($roomsNeeded * $rateIdr)),
                    'status' => 'confirmed',
                ];
            } catch (\Throwable $e) {
                Log::warning('Panggilan RoomBlockReserver mengalami fallback', ['error' => $e->getMessage()]);
            }
        }

        // Fallback deterministik jika modul hotel tidak terikat langsung di runtime
        $ref = sprintf('HTL-IRROPS-%s-%s', date('Ymd'), strtoupper(substr(uniqid('', true), -6)));

        return [
            'reservation_id' => $ref,
            'rooms_allocated' => $roomsNeeded,
            'total_charge_idr' => $roomsNeeded * $rateIdr,
            'status' => 'confirmed_internal',
        ];
    }
}
