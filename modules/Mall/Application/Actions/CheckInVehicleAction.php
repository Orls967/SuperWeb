<?php

declare(strict_types=1);

namespace Modules\Mall\Application\Actions;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Core\Domain\Models\Vehicle;
use Modules\Mall\Domain\Enums\MemberStatus;
use Modules\Mall\Domain\Enums\ParkingPaymentStatus;
use Modules\Mall\Domain\Enums\ParkingSessionStatus;
use Modules\Mall\Domain\Enums\VehicleType;
use Modules\Mall\Domain\Exceptions\ParkingZoneFullException;
use Modules\Mall\Domain\Exceptions\VehicleAlreadyParkedException;
use Modules\Mall\Domain\Models\ParkingMember;
use Modules\Mall\Domain\Models\ParkingSession;
use Modules\Mall\Domain\Models\ParkingZone;
use Modules\Mall\Domain\Models\Property;

class CheckInVehicleAction
{
    /**
     * Catat kendaraan masuk gate parkir mall.
     *
     * Kapasitas zona dikunci di dalam transaksi supaya dua gate yang melayani
     * kendaraan bersamaan tidak bisa melewati kapasitas terpasang.
     *
     * @throws VehicleAlreadyParkedException
     * @throws ParkingZoneFullException
     */
    public function execute(
        Property $property,
        string $plateNumber,
        VehicleType $vehicleType,
        string $entryGate = 'Gate Masuk 1',
        ?int $parkingZoneId = null,
        ?Carbon $entryTime = null
    ): ParkingSession {
        $normalizedPlate = self::normalizePlate($plateNumber);
        $entryTime = $entryTime ?? Carbon::now();

        return DB::transaction(function () use (
            $property,
            $normalizedPlate,
            $vehicleType,
            $entryGate,
            $parkingZoneId,
            $entryTime
        ) {
            // 1. Validasi apakah kendaraan sedang aktif parkir
            $activeSession = ParkingSession::query()
                ->where('plate_number', $normalizedPlate)
                ->where('status', ParkingSessionStatus::ACTIVE)
                ->first();

            if ($activeSession) {
                throw new VehicleAlreadyParkedException(
                    "Kendaraan dengan plat {$normalizedPlate} sedang aktif berada di area parkir (Tiket: {$activeSession->ticket_number})."
                );
            }

            // 2. Tentukan zona parkir dan kunci barisnya
            $zone = $this->resolveZone($property, $vehicleType, $parkingZoneId);

            if ($zone === null) {
                throw new ParkingZoneFullException(
                    "Kapasitas zona parkir untuk kendaraan {$vehicleType->label()} sudah penuh."
                );
            }

            // 3. Cek keanggotaan member parkir.
            //    start_date dibandingkan dengan hari ini (bukan waktu masuk) supaya kendaraan
            //    yang masuk sebelum tengah malam tidak kehilangan status membernya.
            $member = ParkingMember::query()
                ->where('property_id', $property->id)
                ->where('plate_number', $normalizedPlate)
                ->where('status', MemberStatus::ACTIVE)
                // whereDate agar perbandingan tetap benar baik pada MySQL maupun SQLite,
                // karena cast 'date' menyimpan nilai lengkap 'Y-m-d H:i:s'
                ->whereDate('start_date', '<=', Carbon::today()->toDateString())
                ->whereDate('end_date', '>=', $entryTime->toDateString())
                ->first();

            // 4. Cocokkan dengan registri kendaraan (My Garage) bila platnya terdaftar
            $vehicle = Vehicle::query()->where('plate_number', $normalizedPlate)->first();

            $ticketNumber = 'TKT-'.$entryTime->format('YmdHis').'-'.strtoupper(Str::random(4));

            $zone->increment('current_occupancy');

            return ParkingSession::create([
                'ticket_number' => $ticketNumber,
                'property_id' => $property->id,
                'parking_zone_id' => $zone->id,
                'member_id' => $member?->id,
                'vehicle_id' => $vehicle?->id,
                'plate_number' => $normalizedPlate,
                'vehicle_type' => $vehicleType,
                'entry_gate' => $entryGate,
                'entry_time' => $entryTime,
                'duration_minutes' => 0,
                'base_fee' => 0,
                'penalty_fee' => 0,
                'discount_amount' => 0,
                'total_fee' => 0,
                'payment_status' => ParkingPaymentStatus::UNPAID,
                'is_lost_ticket' => false,
                'status' => ParkingSessionStatus::ACTIVE,
            ]);
        }, attempts: 3);
    }

    /**
     * Zona yang masih punya slot, dikunci untuk update. Null bila semua penuh.
     */
    private function resolveZone(Property $property, VehicleType $vehicleType, ?int $parkingZoneId): ?ParkingZone
    {
        $baseQuery = fn () => ParkingZone::query()
            ->where('property_id', $property->id)
            ->where('vehicle_type', $vehicleType)
            ->where('is_active', true)
            ->lockForUpdate();

        if ($parkingZoneId !== null) {
            $zone = $baseQuery()->where('id', $parkingZoneId)->first();

            return ($zone !== null && ! $zone->isFull()) ? $zone : null;
        }

        return $baseQuery()
            ->orderBy('id')
            ->get()
            ->first(fn (ParkingZone $zone) => ! $zone->isFull());
    }

    /**
     * Normalisasi plat nomor agar pencocokan member & kendaraan konsisten.
     */
    public static function normalizePlate(string $plateNumber): string
    {
        $collapsed = preg_replace('/\s+/', ' ', trim($plateNumber));

        return strtoupper($collapsed ?? trim($plateNumber));
    }
}
