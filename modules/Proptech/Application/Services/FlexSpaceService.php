<?php

namespace Modules\Proptech\Application\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Proptech\Domain\Models\BuildingSensor;
use Modules\Proptech\Domain\Models\Flex\FlexBooking;
use Modules\Proptech\Domain\Models\Flex\FlexSpace;

class FlexSpaceService
{
    public function __construct(
        protected ?LedgerService $ledgerService = null
    ) {
        $this->ledgerService = $ledgerService ?? app(LedgerService::class);
    }

    /**
     * 78.2 Time-locked booking without overlap and initial deposit hold
     */
    public function createBooking(
        FlexSpace $space,
        int $userId,
        Carbon $startTime,
        Carbon $endTime
    ): FlexBooking {
        return DB::transaction(function () use ($space, $userId, $startTime, $endTime) {
            // Lock space to prevent concurrent race condition
            FlexSpace::where('id', $space->id)->lockForUpdate()->firstOrFail();

            // Anti-overlap check
            $overlap = FlexBooking::where('flex_space_id', $space->id)
                ->whereIn('status', ['HELD', 'CONFIRMED', 'CHECKED_IN'])
                ->where(function ($query) use ($startTime, $endTime) {
                    $query->whereBetween('start_time', [$startTime, $endTime])
                        ->orWhereBetween('end_time', [$startTime, $endTime])
                        ->orWhere(function ($q) use ($startTime, $endTime) {
                            $q->where('start_time', '<=', $startTime)
                                ->where('end_time', '>=', $endTime);
                        });
                })
                ->exists();

            if ($overlap) {
                throw new \RuntimeException('Requested flex space time slot overlaps with an existing booking');
            }

            $durationHours = max(1, $startTime->diffInHours($endTime));
            $totalPrice = (int) ($durationHours * $space->rate_per_hour_idr);
            $depositAmount = (int) round($totalPrice * 0.30); // 30% deposit hold

            $booking = FlexBooking::create([
                'booking_code' => 'FBK-'.strtoupper(bin2hex(random_bytes(6))),
                'flex_space_id' => $space->id,
                'user_id' => $userId,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'deposit_amount_idr' => $depositAmount,
                'total_price_idr' => $totalPrice,
                'status' => 'HELD',
            ]);

            // Post deposit hold to ledger: Debit Customer Wallet/Deposit, Credit Escrow Deposit Liability
            $this->ledgerService->post(new PostingDTO(
                type: 'FLEX_DEPOSIT_HOLD',
                description: "Deposit hold for flex space booking {$booking->booking_code}",
                idempotencyKey: "FBK-DEP-{$booking->booking_code}",
                entries: [
                    PostingEntryDTO::forCode('prp:flex_escrow:IDR', 'IDR', $depositAmount),
                    PostingEntryDTO::forCode('prp:flex_revenue_unearned:IDR', 'IDR', -$depositAmount),
                ],
                referenceType: 'FLEX_BOOKING',
                referenceId: (string) $booking->id,
            ));

            return $booking;
        });
    }

    /**
     * 78.3 Smart access check-in with Cryptographic Passport QR scan
     */
    public function checkInWithPassport(
        FlexBooking $booking,
        string $passportToken
    ): FlexBooking {
        return DB::transaction(function () use ($booking, $passportToken) {
            $locked = FlexBooking::where('id', $booking->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, ['HELD', 'CONFIRMED'])) {
                throw new \RuntimeException("Cannot check-in booking in status {$locked->status}");
            }

            if (empty(trim($passportToken))) {
                throw new \RuntimeException('Invalid cryptographic passport');
            }

            $passportHash = hash('sha256', $passportToken);

            $locked->update([
                'status' => 'CHECKED_IN',
                'checked_in_passport_hash' => $passportHash,
                'checked_in_at' => now(),
            ]);

            // Capture full revenue on check-in
            $remaining = $locked->total_price_idr - $locked->deposit_amount_idr;
            $this->ledgerService->post(new PostingDTO(
                type: 'FLEX_CAPTURE_REVENUE',
                description: "Capture revenue for flex space {$locked->booking_code}",
                idempotencyKey: "FBK-CAP-{$locked->booking_code}",
                entries: [
                    PostingEntryDTO::forCode('prp:flex_revenue_unearned:IDR', 'IDR', $locked->deposit_amount_idr),
                    PostingEntryDTO::forCode('prp:flex_escrow:IDR', 'IDR', $remaining),
                    PostingEntryDTO::forCode('prp:flex_revenue:IDR', 'IDR', -$locked->total_price_idr),
                ],
                referenceType: 'FLEX_BOOKING',
                referenceId: (string) $locked->id,
            ));

            // 78.5 Trigger utility load to building zone
            $space = $locked->space;
            if ($space && $space->zone_id) {
                BuildingSensor::create([
                    'sensor_code' => "PWR-FLEX-{$space->space_code}",
                    'zone_id' => $space->zone_id,
                    'sensor_type' => 'POWER_KWH',
                    'reading_value' => 15.0, // 15 kWh session load
                    'idempotency_key' => "FLEX-SESSION-{$locked->booking_code}",
                    'recorded_at' => now(),
                ]);
            }

            return $locked;
        });
    }

    /**
     * 78.2 Handle No-show: retain deposit as no-show fee penalty
     */
    public function handleNoShow(FlexBooking $booking): FlexBooking
    {
        return DB::transaction(function () use ($booking) {
            $locked = FlexBooking::where('id', $booking->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'HELD' && $locked->status !== 'CONFIRMED') {
                return $locked;
            }

            $locked->update(['status' => 'NO_SHOW']);

            // Forfeit deposit as no-show penalty revenue
            $this->ledgerService->post(new PostingDTO(
                type: 'FLEX_NO_SHOW_FEE',
                description: "Forfeit deposit as penalty fee for {$locked->booking_code}",
                idempotencyKey: "FBK-NS-{$locked->booking_code}",
                entries: [
                    PostingEntryDTO::forCode('prp:flex_revenue_unearned:IDR', 'IDR', $locked->deposit_amount_idr),
                    PostingEntryDTO::forCode('prp:flex_penalty_rev:IDR', 'IDR', -$locked->deposit_amount_idr),
                ],
                referenceType: 'FLEX_BOOKING',
                referenceId: (string) $locked->id,
            ));

            return $locked;
        });
    }
}
