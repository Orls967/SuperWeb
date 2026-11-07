<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Hotel\Domain\Algorithms\BestAvailableRateCalculator;
use Modules\Hotel\Domain\Algorithms\OverbookingLimitOptimizer;
use Modules\Hotel\Domain\Models\HotelProperty;
use Modules\Hotel\Domain\Models\HotelReservation;
use Modules\Hotel\Domain\Models\HotelRoom;
use Modules\Hotel\Domain\Models\HotelRoomNight;
use Modules\Hotel\Domain\Models\HotelRoomType;
use Modules\Hotel\Domain\Services\HotelExternalFactors;
use Modules\Hotel\Domain\StateMachines\ReservationStateMachine;

/**
 * Aksi Reservasi CRS Terpusat dengan Inventori Pessimistic Lock & Optimasi Overbooking H1 (D91-2)
 */
final class ReserveRoomCentralizedCrsAction
{
    /**
     * @param  string  $checkInDate  (Y-m-d)
     * @param  string  $checkOutDate  (Y-m-d)
     * @param  string  $channel  (web, app, ota, corporate, walk_in)
     * @return array{reservation: HotelReservation, is_overbooked: bool, total_amount_idr: int, nights: int}
     */
    public function execute(
        string|int $propertyId,
        string|int $roomTypeId,
        int|string $guestUserId,
        string $checkInDate,
        string $checkOutDate,
        string $channel = 'web',
        ?int $customRateIdr = null,
        ?string $idempotencyKey = null
    ): array {
        if ($idempotencyKey === null || trim($idempotencyKey) === '') {
            throw new InvalidArgumentException('Idempotency key wajib disertakan.');
        }

        $propertyIdStr = (string) $propertyId;
        $roomTypeIdStr = (string) $roomTypeId;

        // Cek apakah key sudah pernah diproses
        $existing = HotelReservation::where('idempotency_key', $idempotencyKey)->first();
        if ($existing !== null) {
            $nights = (int) CarbonImmutable::parse($existing->check_in_date)->diffInDays(CarbonImmutable::parse($existing->check_out_date));

            return [
                'reservation' => $existing,
                'is_overbooked' => $existing->status === ReservationStateMachine::STATUS_CONFIRMED && (bool) ($existing->is_overbooked ?? false),
                'total_amount_idr' => (int) ($existing->total_room_charge_idr ?? 0),
                'nights' => max(1, $nights),
            ];
        }

        $checkIn = CarbonImmutable::parse($checkInDate);
        $checkOut = CarbonImmutable::parse($checkOutDate);
        if ($checkOut->isBefore($checkIn) || $checkOut->equalTo($checkIn)) {
            throw new InvalidArgumentException('Tanggal check-out harus setelah check-in.');
        }

        $nights = (int) $checkIn->diffInDays($checkOut);

        return DB::transaction(function () use (
            $propertyIdStr,
            $roomTypeIdStr,
            $guestUserId,
            $checkIn,
            $checkOut,
            $nights,
            $channel,
            $customRateIdr,
            $idempotencyKey
        ): array {
            $property = HotelProperty::lockForUpdate()->findOrFail($propertyIdStr);
            $roomType = HotelRoomType::lockForUpdate()->findOrFail($roomTypeIdStr);

            // 1. Hitung total kapasitas fisik tipe kamar di properti ini
            $totalPhysicalRooms = HotelRoom::where('property_id', $propertyIdStr)
                ->where('room_type_id', $roomTypeIdStr)
                ->where('status', '!=', 'out_of_order')
                ->count();

            if ($totalPhysicalRooms <= 0) {
                // Fallback jika belum di-assign spesifik tipe kamar
                $totalPhysicalRooms = HotelRoom::where('property_id', $propertyIdStr)
                    ->where('status', '!=', 'out_of_order')
                    ->count();
            }

            if ($totalPhysicalRooms <= 0) {
                $totalPhysicalRooms = 10; // Default capacity
            }

            // 2. Evaluasi ketersediaan per malam kamar
            $isOverbookedStay = false;
            $dates = [];
            for ($i = 0; $i < $nights; $i++) {
                $dates[] = $checkIn->addDays($i)->format('Y-m-d');
            }

            // Kunci baris room nights
            $bookedPerDate = DB::table('htl_room_nights')
                ->where('property_id', $propertyIdStr)
                ->where('room_type_id', $roomTypeIdStr)
                ->whereIn('night_date', $dates)
                ->whereIn('status', ['HOLD', 'BOOKED', 'OCCUPIED'])
                ->select('night_date', DB::raw('count(*) as booked_count'))
                ->groupBy('night_date')
                ->pluck('booked_count', 'night_date')
                ->all();

            // Ambil batas overbooking H1
            $noShowDist = HotelExternalFactors::getNoShowDistributionBps();
            $basePrice = $customRateIdr ?? 1_000_000;
            $optimizerRes = OverbookingLimitOptimizer::optimize(
                capacity: $totalPhysicalRooms,
                noShowDistributionBps: $noShowDist,
                revenuePerRoomIdr: $basePrice,
                walkCostIdr: (int) ($basePrice * 2.5),
                policyMaxPercentBps: 300 // 3%
            );
            $allowedOverbookCeiling = $optimizerRes['optimal_k'];

            foreach ($dates as $d) {
                $currentBooked = (int) ($bookedPerDate[$d] ?? 0);
                if ($currentBooked >= $totalPhysicalRooms + $allowedOverbookCeiling) {
                    throw new InvalidArgumentException("Kamar penuh dan telah melampaui batas overbooking legal pada tanggal {$d}.");
                }
                if ($currentBooked >= $totalPhysicalRooms) {
                    $isOverbookedStay = true;
                }
            }

            // 3. Tentukan rate per malam via H2 BAR Calculator
            $leadDays = (int) CarbonImmutable::now()->diffInDays($checkIn);
            $city = $property->city ?? 'Bali';
            $isEvent = HotelExternalFactors::isCityEventActive($city);

            $avgBooked = count($bookedPerDate) > 0 ? (array_sum($bookedPerDate) / count($bookedPerDate)) : 0.0;
            $occupancyRatio = $totalPhysicalRooms > 0 ? ($avgBooked / $totalPhysicalRooms) : 0.0;

            $barRes = BestAvailableRateCalculator::calculate(
                baseRateIdr: $basePrice,
                occupancyRate: $occupancyRatio,
                leadDays: $leadDays,
                isEventActive: $isEvent,
                contractRateIdr: ($channel === 'corporate' && $customRateIdr !== null) ? $customRateIdr : null,
                floorRateIdr: 450_000,
                ceilingRateIdr: 3_000_000
            );

            $ratePerNight = $barRes['rate_idr'];
            $totalRoomCharge = $ratePerNight * $nights;

            // Pajak & Service Charge
            $pbjtBps = HotelExternalFactors::getPbjtRateBps($city);
            $serviceBps = HotelExternalFactors::getServiceChargeBps($city);
            $taxAmount = intdiv($totalRoomCharge * $pbjtBps, 10000);
            $serviceAmount = intdiv($totalRoomCharge * $serviceBps, 10000);
            $grandTotal = $totalRoomCharge + $taxAmount + $serviceAmount;

            $resId = (string) Str::uuid();
            $resNumber = 'RSV-'.strtoupper(bin2hex(random_bytes(4)));

            // Buat record reservasi
            $reservation = HotelReservation::create([
                'id' => $resId,
                'property_id' => $propertyIdStr,
                'room_type_id' => $roomTypeIdStr,
                'reservation_number' => $resNumber,
                'guest_user_id' => (string) $guestUserId,
                'check_in_date' => $checkIn->format('Y-m-d'),
                'check_out_date' => $checkOut->format('Y-m-d'),
                'daily_rate_idr' => $ratePerNight,
                'total_room_charge_idr' => $grandTotal,
                'status' => ReservationStateMachine::STATUS_CONFIRMED,
                'idempotency_key' => $idempotencyKey,
            ]);

            // Buat baris room nights
            foreach ($dates as $d) {
                HotelRoomNight::create([
                    'id' => (string) Str::uuid(),
                    'property_id' => $propertyIdStr,
                    'room_type_id' => $roomTypeIdStr,
                    'reservation_id' => $reservation->id,
                    'night_date' => $d,
                    'status' => 'BOOKED',
                ]);
            }

            return [
                'reservation' => $reservation,
                'is_overbooked' => $isOverbookedStay,
                'total_amount_idr' => $grandTotal,
                'nights' => $nights,
            ];
        });
    }
}
