<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Hotel\Application\Services\HotelBusinessDateService;
use Modules\Hotel\Domain\Ledger\HotelPostingHelper;
use Modules\Hotel\Domain\Models\HotelDailyRevenueSnapshot;
use Modules\Hotel\Domain\Models\HotelProperty;
use Modules\Hotel\Domain\Models\HotelReservation;
use Modules\Hotel\Domain\Models\HotelRoomNight;
use Modules\Hotel\Domain\Services\HotelExternalFactors;
use Modules\Hotel\Domain\StateMachines\ReservationStateMachine;

/**
 * Night Audit Harian Berbasis Tanggal Bisnis dengan Penjurnalan Otomatis & Idempotensi (D91-8)
 */
final class RunNightAuditFase91Action
{
    public function __construct(
        private readonly HotelPostingHelper $postingHelper,
        private readonly HotelBusinessDateService $businessDateService
    ) {}

    /**
     * @param  string|null  $overrideBusinessDate  (Y-m-d)
     * @return array{business_date: string, processed_rooms: int, total_revenue_idr: int, no_show_count: int}
     */
    public function execute(
        string|int $propertyId,
        ?string $overrideBusinessDate = null,
        ?string $idempotencyKey = null
    ): array {
        $propertyIdStr = (string) $propertyId;
        $property = HotelProperty::findOrFail($propertyIdStr);
        $businessDate = $overrideBusinessDate ?? $this->businessDateService->getCurrentBusinessDate($propertyIdStr);

        $key = $idempotencyKey ?? "HTL:NIGHT_AUDIT:PROP-{$propertyIdStr}:DATE-{$businessDate}";

        return DB::transaction(function () use ($property, $propertyIdStr, $businessDate, $key): array {
            $dateOnly = CarbonImmutable::parse($businessDate)->toDateString();

            // Cek apakah night audit tanggal ini sudah selesai diproses (idempoten)
            $existingSnapshot = HotelDailyRevenueSnapshot::where('property_id', $propertyIdStr)
                ->whereDate('business_date', $dateOnly)
                ->first();

            if ($existingSnapshot !== null) {
                return [
                    'business_date' => $businessDate,
                    'processed_rooms' => (int) $existingSnapshot->occupied_rooms,
                    'total_revenue_idr' => (int) $existingSnapshot->total_room_revenue_idr,
                    'no_show_count' => 0,
                ];
            }

            // 1. Proses tamu CHECKED_IN pada malam ini: Kenakan room charge & posting ledger
            $activeNights = HotelRoomNight::where('property_id', $propertyIdStr)
                ->where('night_date', $businessDate)
                ->whereIn('status', ['BOOKED', 'OCCUPIED'])
                ->get();

            $totalRoomCharge = 0;
            $processedCount = 0;
            $city = $property->city ?? 'Bali';
            $pbjtBps = HotelExternalFactors::getPbjtRateBps($city);
            $serviceBps = HotelExternalFactors::getServiceChargeBps($city);

            foreach ($activeNights as $rn) {
                $reservation = HotelReservation::lockForUpdate()->find($rn->reservation_id);
                if ($reservation === null) {
                    continue;
                }

                if ($reservation->status === ReservationStateMachine::STATUS_CHECKED_IN) {
                    $charge = (int) ($reservation->daily_rate_idr ?? 1000000);
                    $tax = intdiv($charge * $pbjtBps, 10000);
                    $service = intdiv($charge * $serviceBps, 10000);

                    // Jurnal posting room revenue
                    $this->postingHelper->postNightAuditRoomRevenue(
                        reservationId: (int) abs(crc32($reservation->id)),
                        roomChargeIdr: $charge,
                        taxIdr: $tax,
                        serviceChargeIdr: $service,
                        businessDate: $businessDate,
                        idempotencyKey: "{$key}:RES-{$reservation->id}:POST"
                    );

                    $rn->update(['status' => 'OCCUPIED']);
                    $totalRoomCharge += $charge;
                    $processedCount++;
                } elseif ($reservation->status === ReservationStateMachine::STATUS_CONFIRMED || $reservation->status === ReservationStateMachine::STATUS_RECONFIRMED) {
                    // Tamu tidak datang hingga audit malam -> NO SHOW
                    $reservation->update(['status' => ReservationStateMachine::STATUS_NO_SHOW]);
                    $rn->update(['status' => 'CANCELLED']);
                }
            }

            // Simpan daily revenue snapshot
            HotelDailyRevenueSnapshot::create([
                'property_id' => $propertyIdStr,
                'business_date' => $businessDate,
                'total_rooms' => 100,
                'occupied_rooms' => $processedCount,
                'out_of_order_rooms' => 0,
                'occupancy_rate_bps' => $processedCount * 100,
                'total_room_revenue_idr' => $totalRoomCharge,
                'total_fnb_revenue_idr' => 0,
                'total_tax_pbjt_idr' => (int) round($totalRoomCharge * 0.10),
                'total_service_charge_idr' => (int) round($totalRoomCharge * 0.05),
                'adr_idr' => $processedCount > 0 ? (int) ($totalRoomCharge / $processedCount) : 0,
                'revpar_idr' => (int) ($totalRoomCharge / 100),
            ]);

            // Majukan business date
            $nextDate = CarbonImmutable::parse($businessDate)->addDay()->toDateString();
            $bDateRecord = $this->businessDateService->getBusinessDateRecord($propertyIdStr);
            $bDateRecord->update([
                'current_business_date' => $nextDate,
                'status' => 'open',
                'last_checkpoint' => 'COMPLETED',
                'audited_at' => now(),
            ]);

            return [
                'business_date' => $businessDate,
                'processed_rooms' => $processedCount,
                'total_revenue_idr' => $totalRoomCharge,
                'no_show_count' => 0,
            ];
        });
    }
}
