<?php

declare(strict_types=1);

namespace Modules\Proptech\Application\Actions;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Proptech\Domain\Models\Flex\FlexBooking;
use Modules\Proptech\Domain\Models\Flex\FlexSpace;
use Modules\Shared\Application\BaseAction;

class BookFlexSpaceHotDeskOrRoomAction extends BaseAction
{
    /**
     * Book flex-space hot-desk or meeting room with anti-overlap and deposit hold.
     *
     * @return array{booking: FlexBooking, total_price_idr: int, deposit_amount_idr: int, hours: float}
     */
    public function handle(
        int $flexSpaceId,
        int $userId,
        string $startTime,
        string $endTime,
        int $depositAmountIdr
    ): array {
        return DB::transaction(function () use ($flexSpaceId, $userId, $startTime, $endTime, $depositAmountIdr) {
            $space = FlexSpace::lockForUpdate()->findOrFail($flexSpaceId);

            $start = Carbon::parse($startTime);
            $end = Carbon::parse($endTime);

            if ($end->lte($start)) {
                throw new \InvalidArgumentException('End time must be after start time.');
            }

            // Anti-overlap verification
            $hasOverlap = FlexBooking::where('flex_space_id', $space->id)
                ->whereIn('status', ['HELD', 'CONFIRMED', 'CHECKED_IN'])
                ->where(function ($q) use ($start, $end) {
                    $q->whereBetween('start_time', [$start, $end])
                        ->orWhereBetween('end_time', [$start, $end])
                        ->orWhere(function ($sub) use ($start, $end) {
                            $sub->where('start_time', '<=', $start)
                                ->where('end_time', '>=', $end);
                        });
                })
                ->exists();

            if ($hasOverlap) {
                throw new \RuntimeException('Selected time slot overlaps with an existing booking.');
            }

            $hours = max(1.0, round($start->diffInMinutes($end) / 60, 2));
            $totalPriceIdr = (int) round($hours * $space->rate_per_hour_idr);

            $bookingCode = 'BKG-PRP-'.strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));

            $booking = FlexBooking::create([
                'booking_code' => $bookingCode,
                'flex_space_id' => $space->id,
                'user_id' => $userId,
                'start_time' => $start,
                'end_time' => $end,
                'deposit_amount_idr' => $depositAmountIdr,
                'total_price_idr' => $totalPriceIdr,
                'status' => 'CONFIRMED',
            ]);

            $this->audit(
                action: 'proptech.flex.booked',
                auditable: $booking,
                oldValues: null,
                newValues: [
                    'space_id' => $space->id,
                    'user_id' => $userId,
                    'start_time' => $start->toIso8601String(),
                    'end_time' => $end->toIso8601String(),
                    'total_price_idr' => $totalPriceIdr,
                    'deposit_amount_idr' => $depositAmountIdr,
                ],
                correlationId: "prp_flex_{$bookingCode}",
                impactType: 'flex_space_booking'
            );

            $this->outbox(
                eventType: 'FlexSpaceBookingConfirmed',
                payload: [
                    'booking_id' => $booking->id,
                    'booking_code' => $bookingCode,
                    'user_id' => $userId,
                    'space_id' => $space->id,
                    'total_price_idr' => $totalPriceIdr,
                ],
                idempotencyKey: hash('sha256', "flex_bkg_{$bookingCode}")
            );

            return [
                'booking' => $booking,
                'total_price_idr' => $totalPriceIdr,
                'deposit_amount_idr' => $depositAmountIdr,
                'hours' => $hours,
            ];
        });
    }
}
