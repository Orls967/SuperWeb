<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Services;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Hotel\Domain\Models\HotelRoom;
use Modules\Hotel\Domain\Models\HotelRoomNight;
use Modules\Hotel\Domain\Models\HotelRoomType;

class HotelInventoryService
{
    /**
     * Menghitung sisa kamar yang dapat dijual untuk satu tanggal tertentu.
     * Rumus: Kamar Fisik - OutOfOrder - Terjual + Overbooking Limit
     */
    public function getAvailableCount(string $propertyId, string $roomTypeId, string $date): int
    {
        $roomType = HotelRoomType::findOrFail($roomTypeId);

        $totalPhysical = HotelRoom::where('property_id', $propertyId)
            ->where(function ($q) use ($roomType) {
                $q->where('room_type_id', $roomType->id)
                    ->orWhereRaw('LOWER(room_type) = ?', [strtolower($roomType->code)]);
            })
            ->count();

        $outOfOrderCount = HotelRoom::where('property_id', $propertyId)
            ->where(function ($q) use ($roomType) {
                $q->where('room_type_id', $roomType->id)
                    ->orWhereRaw('LOWER(room_type) = ?', [strtolower($roomType->code)]);
            })
            ->whereIn('status', ['maintenance', 'out_of_order'])
            ->count();

        $soldCount = HotelRoomNight::where('property_id', $propertyId)
            ->where('room_type_id', $roomTypeId)
            ->whereDate('night_date', $date)
            ->whereIn('status', ['held', 'sold'])
            ->count();

        $overbookingBuffer = (int) floor($totalPhysical * ($roomType->overbooking_max_percent / 100));

        $available = ($totalPhysical - $outOfOrderCount - $soldCount) + $overbookingBuffer;

        return max(0, $available);
    }

    /**
     * Memeriksa ketersediaan kamar pada rentang tanggal check-in s/d check-out.
     *
     * @return array{is_available: bool, min_available: int, dates: list<string>}
     */
    public function checkAvailabilityRange(
        string $propertyId,
        string $roomTypeId,
        string $checkInDate,
        string $checkOutDate
    ): array {
        $in = Carbon::parse($checkInDate);
        $out = Carbon::parse($checkOutDate);

        if ($out->lte($in)) {
            throw new \InvalidArgumentException('Check-out date must be after check-in date.');
        }

        $period = CarbonPeriod::create($in, '1 day', $out->copy()->subDay());
        $minAvailable = PHP_INT_MAX;
        $dates = [];

        foreach ($period as $dt) {
            $dateStr = $dt->toDateString();
            $dates[] = $dateStr;
            $avail = $this->getAvailableCount($propertyId, $roomTypeId, $dateStr);
            if ($avail < $minAvailable) {
                $minAvailable = $avail;
            }
        }

        return [
            'is_available' => $minAvailable > 0,
            'min_available' => $minAvailable === PHP_INT_MAX ? 0 : $minAvailable,
            'dates' => $dates,
        ];
    }

    /**
     * Kunci dan alokasikan kamar per malam secara atomik.
     *
     * @return list<HotelRoomNight>
     */
    public function lockAndAssignNights(
        string $propertyId,
        string $roomTypeId,
        ?string $roomId,
        string $reservationId,
        string $checkInDate,
        string $checkOutDate
    ): array {
        return DB::transaction(function () use (
            $propertyId,
            $roomTypeId,
            $roomId,
            $reservationId,
            $checkInDate,
            $checkOutDate
        ) {
            $roomType = HotelRoomType::lockForUpdate()->findOrFail($roomTypeId);
            $check = $this->checkAvailabilityRange($propertyId, $roomTypeId, $checkInDate, $checkOutDate);

            if (! $check['is_available']) {
                throw new \RuntimeException("Kamar tipe {$roomType->name} tidak tersedia pada tanggal yang dipilih.");
            }

            $assigned = [];
            foreach ($check['dates'] as $nightDate) {
                $rn = HotelRoomNight::create([
                    'id' => (string) Str::uuid(),
                    'property_id' => $propertyId,
                    'room_type_id' => $roomTypeId,
                    'room_id' => $roomId,
                    'reservation_id' => $reservationId,
                    'night_date' => $nightDate,
                    'status' => 'sold',
                ]);
                $assigned[] = $rn;
            }

            return $assigned;
        });
    }

    /**
     * Melepaskan inventaris malam yang terikat pada reservasi.
     */
    public function releaseNights(string $reservationId, ?string $fromDate = null): int
    {
        $query = HotelRoomNight::where('reservation_id', $reservationId);
        if ($fromDate !== null) {
            $query->whereDate('night_date', '>=', $fromDate);
        }

        return $query->delete();
    }
}
