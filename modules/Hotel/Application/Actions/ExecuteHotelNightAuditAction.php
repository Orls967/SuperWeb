<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Actions;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Hotel\Application\Services\HotelBusinessDateService;
use Modules\Hotel\Application\Services\HotelInventoryService;
use Modules\Hotel\Domain\Models\HotelDailyRevenueSnapshot;
use Modules\Hotel\Domain\Models\HotelDeposit;
use Modules\Hotel\Domain\Models\HotelFolio;
use Modules\Hotel\Domain\Models\HotelFolioLine;
use Modules\Hotel\Domain\Models\HotelProperty;
use Modules\Hotel\Domain\Models\HotelRateCalendar;
use Modules\Hotel\Domain\Models\HotelReservation;
use Modules\Hotel\Domain\Models\HotelRoom;
use Modules\Hotel\Domain\Models\HotelRoomNight;
use Modules\Hotel\Ledger\HotelTransactionTypes;
use Modules\Shared\Application\BaseAction;

class ExecuteHotelNightAuditAction extends BaseAction
{
    public function __construct(
        private readonly HotelBusinessDateService $businessDateService,
        private readonly HotelInventoryService $inventoryService,
        private readonly ?Ledger $ledger = null,
    ) {}

    /**
     * Menjalankan siklus night audit bertahap dengan mekanisme checkpoint.
     */
    public function execute(string $propertyId, ?string $targetDate = null): HotelDailyRevenueSnapshot
    {
        return DB::transaction(function () use ($propertyId, $targetDate) {
            $businessDateRecord = $this->businessDateService->getBusinessDateRecord($propertyId);
            $currentDate = $targetDate ?? Carbon::parse($businessDateRecord->current_business_date)->toDateString();
            $property = HotelProperty::findOrFail($propertyId);

            // Cek jika sudah pernah diaudit pada tanggal ini (idempotensi)
            $existingSnapshot = HotelDailyRevenueSnapshot::where('property_id', $propertyId)
                ->whereDate('business_date', $currentDate)
                ->first();

            if ($existingSnapshot && $businessDateRecord->last_checkpoint === 'COMPLETED') {
                return $existingSnapshot;
            }

            // Kunci status ke 'auditing'
            $businessDateRecord->update([
                'status' => 'auditing',
                'last_checkpoint' => $businessDateRecord->last_checkpoint ?? 'STARTED',
            ]);

            $serviceChargeBps = $property->service_charge_bps ?? 1000;
            $taxPbjtBps = $property->tax_pbjt_bps ?? 1000;

            // ========================================================
            // CHECKPOINT 1: POST_ROOM_CHARGES (Hanya untuk checked_in)
            // ========================================================
            if (in_array($businessDateRecord->last_checkpoint, ['STARTED', 'POST_ROOM_CHARGES'])) {
                $occupiedReservations = HotelReservation::with(['folio', 'room'])
                    ->where('property_id', $propertyId)
                    ->where('status', 'checked_in')
                    ->get();

                foreach ($occupiedReservations as $reservation) {
                    $folio = $reservation->folio ?? HotelFolio::where('reservation_id', $reservation->id)->first();
                    if (! $folio) {
                        continue;
                    }

                    $nightKey = "na_room_{$propertyId}_{$currentDate}_{$reservation->id}";

                    // Idempotensi per kamar per malam
                    $existingLine = HotelFolioLine::where('folio_id', $folio->id)
                        ->where('idempotency_key', $nightKey)
                        ->first();

                    if ($existingLine) {
                        continue;
                    }

                    // Tentukan tarif harian kamar
                    $baseRate = $reservation->daily_rate_idr;
                    if ($baseRate <= 0) {
                        $calRate = HotelRateCalendar::where('property_id', $propertyId)
                            ->where('room_type_id', $reservation->room_type_id)
                            ->where('calendar_date', $currentDate)
                            ->value('rate_idr');

                        $baseRate = $calRate ?? ($reservation->room->base_rate_idr ?? 1000000);
                    }

                    // H-3: service charge 10% dari tarif dasar, PBJT 10% dari (tarif dasar + service)
                    $serviceCharge = (int) round($baseRate * $serviceChargeBps / 10000, 0, PHP_ROUND_HALF_UP);
                    $taxPbjt = (int) round(($baseRate + $serviceCharge) * $taxPbjtBps / 10000, 0, PHP_ROUND_HALF_UP);
                    $totalNightAmount = $baseRate + $serviceCharge + $taxPbjt;

                    $lineId = (string) Str::uuid();

                    if ($this->ledger) {
                        $this->ledger->post(new PostingDTO(
                            type: HotelTransactionTypes::HOTEL_NIGHT_AUDIT_ROOM->value,
                            description: "Night audit room charge: {$reservation->reservation_number} ({$currentDate})",
                            idempotencyKey: $nightKey,
                            entries: [
                                PostingEntryDTO::forCode('htl:guest_ledger:IDR', 'IDR', -$totalNightAmount),
                                PostingEntryDTO::forCode('htl:room_revenue:IDR', 'IDR', $baseRate),
                                PostingEntryDTO::forCode('htl:service_charge_payable:IDR', 'IDR', $serviceCharge),
                                PostingEntryDTO::forCode('htl:tax_payable:IDR', 'IDR', $taxPbjt),
                            ],
                            referenceType: 'htl_folio_lines',
                            referenceId: $lineId,
                        ));
                    }

                    HotelFolioLine::create([
                        'id' => $lineId,
                        'folio_id' => $folio->id,
                        'business_date' => $currentDate,
                        'line_type' => 'ROOM_CHARGE',
                        'description' => "Biaya Kamar ({$currentDate})",
                        'amount_idr' => $totalNightAmount,
                        'base_amount_idr' => $baseRate,
                        'service_charge_idr' => $serviceCharge,
                        'tax_pbjt_idr' => $taxPbjt,
                        'reversal_of_id' => null,
                        'idempotency_key' => $nightKey,
                    ]);

                    $folio->total_room_charges += $totalNightAmount;
                    $folio->outstanding_balance += $totalNightAmount;
                    $folio->save();
                }

                $businessDateRecord->update(['last_checkpoint' => 'POST_ROOM_CHARGES']);
            }

            // ========================================================
            // CHECKPOINT 2: PROCESS_NO_SHOWS (H-7)
            // ========================================================
            if (in_array($businessDateRecord->last_checkpoint, ['POST_ROOM_CHARGES', 'PROCESS_NO_SHOWS'])) {
                // Cari reservasi confirmed yang tanggal check-in <= currentDate tapi belum check-in
                $noShowReservations = HotelReservation::where('property_id', $propertyId)
                    ->where('status', 'confirmed')
                    ->whereDate('check_in_date', '<=', $currentDate)
                    ->get();

                foreach ($noShowReservations as $noShow) {
                    $noShow->update(['status' => 'no_show']);

                    // Lepaskan room nights yang tersisa ke inventaris jual
                    HotelRoomNight::where('reservation_id', $noShow->id)
                        ->whereDate('night_date', '>', $currentDate)
                        ->delete();

                    // Denda satu malam dipotong dari deposit (bila ada deposit)
                    $deposit = HotelDeposit::where('reservation_id', $noShow->id)
                        ->where('status', 'HELD')
                        ->first();

                    $penaltyAmount = $noShow->daily_rate_idr > 0 ? $noShow->daily_rate_idr : 1000000;

                    if ($deposit) {
                        $deductible = min($penaltyAmount, $deposit->amount_idr - $deposit->applied_amount_idr);
                        if ($deductible > 0) {
                            $noShowKey = "na_noshow_{$propertyId}_{$currentDate}_{$noShow->id}";

                            if ($this->ledger) {
                                $this->ledger->post(new PostingDTO(
                                    type: HotelTransactionTypes::HOTEL_NO_SHOW_PENALTY->value,
                                    description: "No-show penalty 1 night: {$noShow->reservation_number}",
                                    idempotencyKey: $noShowKey,
                                    entries: [
                                        PostingEntryDTO::forCode('htl:guest_deposit:IDR', 'IDR', -$deductible),
                                        PostingEntryDTO::forCode('htl:cancellation_fee_revenue:IDR', 'IDR', $deductible),
                                    ],
                                    referenceType: 'htl_reservations',
                                    referenceId: $noShow->id,
                                ));
                            }

                            $deposit->applied_amount_idr += $deductible;
                            $deposit->status = ($deposit->applied_amount_idr >= $deposit->amount_idr) ? 'APPLIED' : 'PARTIALLY_REFUNDED';
                            $deposit->save();
                        }
                    }
                }

                $businessDateRecord->update(['last_checkpoint' => 'PROCESS_NO_SHOWS']);
            }

            // ========================================================
            // CHECKPOINT 3: DAILY_SNAPSHOT (ADR, RevPAR, Occupancy)
            // ========================================================
            if (in_array($businessDateRecord->last_checkpoint, ['PROCESS_NO_SHOWS', 'DAILY_SNAPSHOT'])) {
                $totalRooms = HotelRoom::where('property_id', $propertyId)->count();
                $outOfOrderRooms = HotelRoom::where('property_id', $propertyId)->where('status', 'out_of_order')->count();
                $occupiedRooms = HotelRoom::where('property_id', $propertyId)->where('status', 'occupied')->count();

                $availableRooms = max(0, $totalRooms - $outOfOrderRooms);
                $occupancyRateBps = $availableRooms > 0 ? (int) round(($occupiedRooms / $availableRooms) * 10000) : 0;

                // Total revenue hari ini dari folio lines
                $roomRevenue = (int) HotelFolioLine::whereHas('folio.reservation', fn ($q) => $q->where('property_id', $propertyId))
                    ->whereDate('business_date', $currentDate)
                    ->where('line_type', 'ROOM_CHARGE')
                    ->sum('base_amount_idr');

                $fnbRevenue = (int) HotelFolioLine::whereHas('folio.reservation', fn ($q) => $q->where('property_id', $propertyId))
                    ->whereDate('business_date', $currentDate)
                    ->where('line_type', 'FNB_OUTLET')
                    ->sum('base_amount_idr');

                $serviceChargeTotal = (int) HotelFolioLine::whereHas('folio.reservation', fn ($q) => $q->where('property_id', $propertyId))
                    ->whereDate('business_date', $currentDate)
                    ->sum('service_charge_idr');

                $taxPbjtTotal = (int) HotelFolioLine::whereHas('folio.reservation', fn ($q) => $q->where('property_id', $propertyId))
                    ->whereDate('business_date', $currentDate)
                    ->sum('tax_pbjt_idr');

                $adr = $occupiedRooms > 0 ? (int) round($roomRevenue / $occupiedRooms) : 0;
                $revpar = $availableRooms > 0 ? (int) round($roomRevenue / $availableRooms) : 0;

                $snapshot = HotelDailyRevenueSnapshot::updateOrCreate(
                    [
                        'property_id' => $propertyId,
                        'business_date' => $currentDate,
                    ],
                    [
                        'total_rooms' => $totalRooms,
                        'occupied_rooms' => $occupiedRooms,
                        'out_of_order_rooms' => $outOfOrderRooms,
                        'occupancy_rate_bps' => $occupancyRateBps,
                        'total_room_revenue_idr' => $roomRevenue,
                        'total_fnb_revenue_idr' => $fnbRevenue,
                        'total_tax_pbjt_idr' => $taxPbjtTotal,
                        'total_service_charge_idr' => $serviceChargeTotal,
                        'adr_idr' => $adr,
                        'revpar_idr' => $revpar,
                    ]
                );

                $businessDateRecord->update(['last_checkpoint' => 'DAILY_SNAPSHOT']);
            } else {
                $snapshot = HotelDailyRevenueSnapshot::where('property_id', $propertyId)
                    ->whereDate('business_date', $currentDate)
                    ->firstOrFail();
            }

            // ========================================================
            // CHECKPOINT 4: ADVANCE_BUSINESS_DATE (H-2)
            // ========================================================
            $nextDate = Carbon::parse($currentDate)->addDay()->toDateString();
            $businessDateRecord->update([
                'current_business_date' => $nextDate,
                'status' => 'open',
                'last_checkpoint' => 'COMPLETED',
                'audited_at' => now(),
            ]);

            return $snapshot;
        });
    }

    /**
     * Helper untuk mensimulasikan interupsi kegagalan pada checkpoint tertentu.
     */
    public function simulateCheckpointFailure(string $propertyId, string $failAtCheckpoint): void
    {
        $record = $this->businessDateService->getBusinessDateRecord($propertyId);
        $record->update([
            'status' => 'auditing',
            'last_checkpoint' => $failAtCheckpoint,
        ]);
    }
}
