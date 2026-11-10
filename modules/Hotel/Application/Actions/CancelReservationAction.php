<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Actions;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Core\Contracts\ClockInterface;
use Modules\Hotel\Application\Services\HotelBusinessDateService;
use Modules\Hotel\Domain\Exceptions\HotelRuleViolationException;
use Modules\Hotel\Domain\Models\HotelDeposit;
use Modules\Hotel\Domain\Models\HotelRatePlan;
use Modules\Hotel\Domain\Models\HotelReservation;
use Modules\Hotel\Domain\Models\HotelRoomNight;
use Modules\Hotel\Ledger\HotelTransactionTypes;
use Modules\Shared\Application\BaseAction;

class CancelReservationAction extends BaseAction
{
    protected HotelBusinessDateService $businessDateService;

    public function __construct(
        protected ?Ledger $ledger = null,
        protected ?ClockInterface $clock = null,
        ?HotelBusinessDateService $businessDateService = null
    ) {
        $this->businessDateService = $businessDateService ?? app(HotelBusinessDateService::class);
    }

    /**
     * @return array{
     *     reservation: HotelReservation,
     *     penalty_idr: int,
     *     deposit_refunded_idr: int,
     *     is_free: bool
     * }
     */
    public function execute(
        string $reservationId,
        string $idempotencyKey,
        ?string $reason = null,
        ?string $cancellationTime = null,
    ): array {
        if (trim($idempotencyKey) === '') {
            throw new \InvalidArgumentException('Idempotency-Key diperlukan untuk pembatalan reservasi.');
        }

        return DB::transaction(function () use (
            $reservationId,
            $idempotencyKey,
            $cancellationTime,
        ) {
            $reservation = HotelReservation::lockForUpdate()->findOrFail($reservationId);

            if ($reservation->status === 'checked_in') {
                throw new HotelRuleViolationException("Pembatalan ditolak: reservasi sudah dalam status 'checked_in'.", 422);
            }

            if (in_array($reservation->status, ['checked_out', 'selesai', 'no_show'])) {
                throw new HotelRuleViolationException("Reservasi berstatus '{$reservation->status}' tidak dapat dibatalkan.", 422);
            }

            if (in_array($reservation->status, ['dibatalkan', 'dibatalkan_denda'])) {
                $isFree = $reservation->status === 'dibatalkan';
                $penalty = $isFree ? 0 : ($reservation->daily_rate_idr > 0 ? (int) $reservation->daily_rate_idr : 1000000);

                return [
                    'reservation' => $reservation,
                    'penalty_idr' => $penalty,
                    'deposit_refunded_idr' => 0,
                    'is_free' => $isFree,
                ];
            }

            $ratePlan = $reservation->rate_plan_id
                ? HotelRatePlan::find($reservation->rate_plan_id)
                : null;

            $isNonRefundable = $ratePlan && (
                strtoupper((string) $ratePlan->plan_type) === 'NON_REFUNDABLE'
                || strtoupper((string) $ratePlan->code) === 'NON_REFUNDABLE'
                || $ratePlan->free_cancellation_hours === 0
            );

            $businessDate = $cancellationTime
                ? Carbon::parse($cancellationTime)->toDateString()
                : $this->businessDateService->getCurrentBusinessDate($reservation->property_id);
            $checkInDate = Carbon::parse($reservation->check_in_date)->toDateString();

            if ($isNonRefundable) {
                $isFree = false;
                $penaltyAmount = $reservation->total_room_charge_idr > 0
                    ? (int) $reservation->total_room_charge_idr
                    : (int) ($reservation->daily_rate_idr > 0 ? $reservation->daily_rate_idr : 1000000);
            } else {
                // Hitung batas waktu pembatalan gratis berdasarkan jam batas & business date
                $checkInDateTime = Carbon::parse($reservation->check_in_date)->setTime(14, 0, 0);
                $freeHours = $ratePlan->free_cancellation_hours ?? 24;
                $deadline = $checkInDateTime->copy()->subHours($freeHours);

                $clock = $this->clock ?? app(ClockInterface::class);
                $now = $cancellationTime ? Carbon::parse($cancellationTime) : Carbon::instance($clock->now());

                $isFree = ($freeHours > 0) && ($now->lessThanOrEqualTo($deadline)) && ($businessDate < $checkInDate);
                $penaltyAmount = 0;

                if (! $isFree) {
                    $penaltyNights = $ratePlan->penalty_nights ?? 1;
                    $dailyRate = $reservation->daily_rate_idr > 0 ? $reservation->daily_rate_idr : 1000000;
                    $penaltyAmount = $penaltyNights * $dailyRate;
                }
            }

            // Lepaskan room nights yang dipesan ke inventaris
            HotelRoomNight::where('reservation_id', $reservation->id)->delete();

            // Tangani deposit tamu jika ada
            $deposit = HotelDeposit::where('reservation_id', $reservation->id)
                ->where('status', 'HELD')
                ->first();

            $refundedDeposit = 0;

            if ($deposit) {
                if ($penaltyAmount > 0) {
                    $deductible = min($penaltyAmount, $deposit->amount_idr - $deposit->applied_amount_idr);
                    if ($deductible > 0 && $this->ledger) {
                        $this->ledger->post(new PostingDTO(
                            type: HotelTransactionTypes::HOTEL_CANCELLATION_PENALTY->value,
                            description: "Denda pembatalan reservasi {$reservation->reservation_number}",
                            idempotencyKey: "htl:cancel:pen:{$idempotencyKey}",
                            entries: [
                                PostingEntryDTO::forCode('htl:guest_deposit:IDR', 'IDR', -$deductible),
                                PostingEntryDTO::forCode('htl:cancellation_fee_revenue:IDR', 'IDR', $deductible),
                            ],
                            referenceType: 'htl_reservations',
                            referenceId: $reservation->id,
                        ));

                        $deposit->applied_amount_idr += $deductible;
                    }

                    // Sisa deposit jika ada dikembalikan penuh
                    $remaining = $deposit->amount_idr - $deposit->applied_amount_idr - $deposit->refunded_amount_idr;
                    if ($remaining > 0) {
                        if ($this->ledger) {
                            $this->ledger->post(new PostingDTO(
                                type: HotelTransactionTypes::HOTEL_DEPOSIT_REFUND->value,
                                description: "Refund sisa deposit pembatalan {$reservation->reservation_number}",
                                idempotencyKey: "htl:cancel:ref:{$idempotencyKey}",
                                entries: [
                                    PostingEntryDTO::forCode('htl:guest_deposit:IDR', 'IDR', -$remaining),
                                    PostingEntryDTO::forCode('clearing:external:IDR', 'IDR', $remaining),
                                ],
                                referenceType: 'htl_deposits',
                                referenceId: $deposit->id,
                            ));
                        }

                        $deposit->refunded_amount_idr += $remaining;
                        $refundedDeposit = $remaining;
                    }

                    $deposit->status = ($deposit->applied_amount_idr + $deposit->refunded_amount_idr >= $deposit->amount_idr)
                        ? ($refundedDeposit > 0 ? 'PARTIALLY_REFUNDED' : 'APPLIED')
                        : 'HELD';
                    $deposit->save();
                } else {
                    // Pembatalan bebas denda: deposit dikembalikan penuh
                    $fullRefund = $deposit->amount_idr - $deposit->applied_amount_idr - $deposit->refunded_amount_idr;
                    if ($fullRefund > 0) {
                        if ($this->ledger) {
                            $this->ledger->post(new PostingDTO(
                                type: HotelTransactionTypes::HOTEL_DEPOSIT_REFUND->value,
                                description: "Refund deposit penuh pembatalan gratis {$reservation->reservation_number}",
                                idempotencyKey: "htl:cancel:free:{$idempotencyKey}",
                                entries: [
                                    PostingEntryDTO::forCode('htl:guest_deposit:IDR', 'IDR', -$fullRefund),
                                    PostingEntryDTO::forCode('clearing:external:IDR', 'IDR', $fullRefund),
                                ],
                                referenceType: 'htl_deposits',
                                referenceId: $deposit->id,
                            ));
                        }

                        $deposit->refunded_amount_idr += $fullRefund;
                        $deposit->status = 'REFUNDED';
                        $deposit->save();
                        $refundedDeposit = $fullRefund;
                    }
                }
            }

            $newStatus = $penaltyAmount > 0 ? 'dibatalkan_denda' : 'dibatalkan';
            $reservation->update(['status' => $newStatus]);

            return [
                'reservation' => $reservation,
                'penalty_idr' => $penaltyAmount,
                'deposit_refunded_idr' => $refundedDeposit,
                'is_free' => $isFree,
            ];
        });
    }
}
