<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Actions;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Core\Contracts\DocumentNumberingInterface;
use Modules\Hotel\Application\DTOs\ReserveRoomDTO;
use Modules\Hotel\Application\Services\HotelInventoryService;
use Modules\Hotel\Domain\Models\HotelFolio;
use Modules\Hotel\Domain\Models\HotelProperty;
use Modules\Hotel\Domain\Models\HotelRateCalendar;
use Modules\Hotel\Domain\Models\HotelRatePlan;
use Modules\Hotel\Domain\Models\HotelReservation;
use Modules\Hotel\Domain\Models\HotelRoom;
use Modules\Hotel\Domain\Models\HotelRoomType;
use Modules\Shared\Application\BaseAction;

class ReserveRoomAction extends BaseAction
{
    public function __construct(
        protected HotelInventoryService $inventoryService,
        protected ?DocumentNumberingInterface $documentNumbering = null
    ) {}

    public function execute(ReserveRoomDTO $dto): HotelReservation
    {
        $result = $this->handle($dto);

        return $result['reservation'];
    }

    /**
     * @return array{
     *     reservation: HotelReservation,
     *     folio: HotelFolio,
     *     nights: int,
     *     daily_rate_idr: int,
     *     total_room_charge_idr: int,
     *     is_overbooked: bool
     * }
     */
    public function handle(ReserveRoomDTO $dto): array
    {
        if (empty(trim($dto->idempotencyKey))) {
            throw new \InvalidArgumentException('Idempotency-Key wajib disertakan.');
        }

        // Idempotency check: jika key sudah pernah diproses, return hasil yang sama
        $existing = HotelReservation::where('idempotency_key', $dto->idempotencyKey)->first();
        if ($existing) {
            $folio = HotelFolio::where('reservation_id', $existing->id)->firstOrFail();
            $in = Carbon::parse($existing->check_in_date);
            $out = Carbon::parse($existing->check_out_date);
            $nights = max(1, (int) $in->diffInDays($out));

            return [
                'reservation' => $existing,
                'folio' => $folio,
                'nights' => $nights,
                'daily_rate_idr' => (int) $existing->daily_rate_idr,
                'total_room_charge_idr' => (int) $existing->total_room_charge_idr,
                'is_overbooked' => false,
            ];
        }

        return DB::transaction(function () use ($dto) {
            $property = HotelProperty::findOrFail($dto->propertyId);
            $roomType = HotelRoomType::where('property_id', $property->id)->findOrFail($dto->roomTypeId);
            $ratePlan = HotelRatePlan::where('property_id', $property->id)->findOrFail($dto->ratePlanId);

            $in = Carbon::parse($dto->checkInDate);
            $out = Carbon::parse($dto->checkOutDate);

            if ($out->lte($in)) {
                throw new \InvalidArgumentException('Check-out date must be after check-in date.');
            }

            // Validasi Rate Plan yang butuh deposit
            if ($ratePlan->requires_deposit && ($dto->depositAmountIdr === null || $dto->depositAmountIdr <= 0)) {
                throw new \InvalidArgumentException("Paket tarif {$ratePlan->name} mewajibkan pembayaran deposit di muka.");
            }

            // Periksa ketersediaan & hitung tarif per malam dari Rate Calendar
            $period = CarbonPeriod::create($in, '1 day', $out->copy()->subDay());
            $nights = max(1, (int) $in->diffInDays($out));
            $totalExpected = 0;

            foreach ($period as $dt) {
                $dateStr = $dt->toDateString();
                $calendarRate = HotelRateCalendar::where('property_id', $property->id)
                    ->where('room_type_id', $roomType->id)
                    ->where('rate_plan_id', $ratePlan->id)
                    ->whereDate('calendar_date', $dateStr)
                    ->value('rate_idr');

                if ($calendarRate !== null) {
                    $totalExpected += (int) $calendarRate;
                } else {
                    // Fallback to room physical base rate
                    $baseRate = HotelRoom::where('property_id', $property->id)
                        ->where(function ($q) use ($roomType) {
                            $q->where('room_type_id', $roomType->id)
                                ->orWhereRaw('LOWER(room_type) = ?', [strtolower($roomType->code)]);
                        })
                        ->value('base_rate_idr') ?? 1000000;
                    $totalExpected += (int) $baseRate;
                }
            }

            $avgDailyRate = (int) round($totalExpected / $nights);

            // Tentukan nomor kamar fisik jika ada
            $roomId = $dto->roomId;
            if (! $roomId) {
                $roomId = HotelRoom::where('property_id', $property->id)
                    ->where(function ($q) use ($roomType) {
                        $q->where('room_type_id', $roomType->id)
                            ->orWhereRaw('LOWER(room_type) = ?', [strtolower($roomType->code)]);
                    })
                    ->where('status', 'available')
                    ->value('id');

                if (! $roomId) {
                    $roomId = HotelRoom::where('property_id', $property->id)
                        ->where(function ($q) use ($roomType) {
                            $q->where('room_type_id', $roomType->id)
                                ->orWhereRaw('LOWER(room_type) = ?', [strtolower($roomType->code)]);
                        })
                        ->value('id');
                }
            }

            // Cek overbooking status
            $totalPhysical = HotelRoom::where('property_id', $property->id)
                ->where(function ($q) use ($roomType) {
                    $q->where('room_type_id', $roomType->id)
                        ->orWhereRaw('LOWER(room_type) = ?', [strtolower($roomType->code)]);
                })
                ->count();

            $availCheck = $this->inventoryService->checkAvailabilityRange(
                $property->id,
                $roomType->id,
                $dto->checkInDate,
                $dto->checkOutDate
            );

            if (! $availCheck['is_available']) {
                throw new \RuntimeException('Ketersediaan kamar tidak mencukupi untuk periode yang dipilih.');
            }

            $isOverbooked = $availCheck['min_available'] <= (int) floor($totalPhysical * ($roomType->overbooking_max_percent / 100))
                && $roomType->overbooking_max_percent > 0;

            // Generate nomor dokumen sekuensial
            if ($this->documentNumbering) {
                $resNumber = $this->documentNumbering->nextNumber(
                    entityCode: 'HTL',
                    documentType: 'RSV',
                    resetMonthly: true,
                    customPrefix: 'RSV-'.date('Ym').'-'
                );
            } else {
                $resNumber = 'RSV-'.date('Ym').'-'.strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
            }

            $reservationId = (string) Str::uuid();

            $reservation = HotelReservation::create([
                'id' => $reservationId,
                'property_id' => $property->id,
                'room_type_id' => $roomType->id,
                'rate_plan_id' => $ratePlan->id,
                'room_id' => $roomId,
                'reservation_number' => $resNumber,
                'guest_user_id' => $dto->guestUserId,
                'check_in_date' => $in->toDateString(),
                'check_out_date' => $out->toDateString(),
                'daily_rate_idr' => $avgDailyRate,
                'total_room_charge_idr' => $totalExpected,
                'status' => 'confirmed',
                'idempotency_key' => $dto->idempotencyKey,
            ]);

            // Kunci inventaris per malam
            $this->inventoryService->lockAndAssignNights(
                propertyId: $property->id,
                roomTypeId: $roomType->id,
                roomId: $roomId,
                reservationId: $reservationId,
                checkInDate: $dto->checkInDate,
                checkOutDate: $dto->checkOutDate
            );

            // Buat folio tamu awal (H-5: pendapatan kamar diposting saat night audit, bukan reservasi)
            $folio = HotelFolio::create([
                'id' => (string) Str::uuid(),
                'reservation_id' => $reservation->id,
                'guest_user_id' => $dto->guestUserId,
                'total_room_charges' => 0,
                'total_addon_charges' => 0,
                'total_paid' => 0,
                'outstanding_balance' => 0,
                'status' => 'open',
            ]);

            $this->audit(
                action: 'hotel.reservation.created',
                auditable: $reservation,
                oldValues: null,
                newValues: [
                    'property_id' => $property->id,
                    'room_type' => $roomType->code,
                    'reservation_number' => $resNumber,
                    'nights' => $nights,
                    'total_expected_idr' => $totalExpected,
                    'is_overbooked' => $isOverbooked,
                ],
                correlationId: "htl_res_{$resNumber}",
                impactType: 'hotel_reservation'
            );

            $this->outbox(
                eventType: 'HotelReservationCreated',
                payload: [
                    'reservation_id' => $reservation->id,
                    'reservation_number' => $resNumber,
                    'property_id' => $property->id,
                    'total_room_charge_idr' => $totalExpected,
                ],
                idempotencyKey: $dto->idempotencyKey
            );

            return [
                'reservation' => $reservation,
                'folio' => $folio,
                'nights' => $nights,
                'daily_rate_idr' => $avgDailyRate,
                'total_room_charge_idr' => $totalExpected,
                'is_overbooked' => $isOverbooked,
            ];
        });
    }
}
