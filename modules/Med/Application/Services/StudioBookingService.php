<?php

declare(strict_types=1);

namespace Modules\Med\Application\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Med\Domain\Models\MedStudio;
use Modules\Med\Domain\Models\MedStudioBooking;
use Modules\Med\Domain\Models\MedStudioSlotClaim;
use RuntimeException;

class StudioBookingService
{
    public const int SLOT_MINUTES = 30;

    /**
     * Reserve and hold 30-minute studio slots atomically with pessimistic lock.
     */
    public function holdBooking(array $params): MedStudioBooking
    {
        if (empty($params['studio_id'])) {
            throw new InvalidArgumentException('Studio ID is required.');
        }

        if (empty($params['client_entity_id'])) {
            throw new InvalidArgumentException('Client entity ID is required.');
        }

        $idempKey = $params['idempotency_key'] ?? null;
        if ($idempKey) {
            $existing = MedStudioBooking::where('idempotency_key', $idempKey)->first();
            if ($existing) {
                return $existing;
            }
        }

        $startTime = CarbonImmutable::parse($params['start_time']);
        $endTime = CarbonImmutable::parse($params['end_time']);

        if ($endTime->lessThanOrEqualTo($startTime)) {
            throw new InvalidArgumentException("End time [{$endTime}] must be after start time [{$startTime}].");
        }

        $durationMinutes = (int) $startTime->diffInMinutes($endTime);
        if ($durationMinutes < self::SLOT_MINUTES || $durationMinutes % self::SLOT_MINUTES !== 0) {
            throw new InvalidArgumentException("Booking duration ({$durationMinutes} mins) must be a positive multiple of ".self::SLOT_MINUTES.' minutes.');
        }

        $studio = MedStudio::findOrFail($params['studio_id']);
        if ($studio->status === 'MAINTENANCE') {
            throw new RuntimeException("Studio [{$studio->studio_code}] is currently under maintenance.");
        }

        // Generate discrete 30-minute slots
        $slots = [];
        $cursor = $startTime;
        while ($cursor->lessThan($endTime)) {
            $slotEnd = $cursor->addMinutes(self::SLOT_MINUTES);
            $slots[] = [
                'start' => $cursor->format('Y-m-d H:i:s'),
                'end' => $slotEnd->format('Y-m-d H:i:s'),
            ];
            $cursor = $slotEnd;
        }

        return DB::transaction(function () use ($params, $studio, $startTime, $endTime, $slots, $idempKey, $durationMinutes) {
            // Lock and inspect conflicting slot claims for this studio
            $slotStarts = array_column($slots, 'start');

            $existingClaims = MedStudioSlotClaim::where('studio_id', $studio->id)
                ->whereIn('slot_start', $slotStarts)
                ->lockForUpdate()
                ->count();

            if ($existingClaims > 0) {
                throw new RuntimeException("Studio booking clash detected for studio {$studio->id}.");
            }

            $hours = $durationMinutes / 60.0;
            $totalPrice = (int) round($hours * $studio->hourly_rate_minor);
            $holdAmount = (int) round($totalPrice * 0.20); // 20% hold

            $booking = MedStudioBooking::create([
                'id' => (string) Str::uuid(),
                'booking_code' => 'BKG-'.strtoupper(Str::random(8)),
                'studio_id' => $studio->id,
                'project_id' => $params['project_id'] ?? null,
                'client_entity_id' => $params['client_entity_id'],
                'start_time' => $startTime->toDateTimeString(),
                'end_time' => $endTime->toDateTimeString(),
                'hold_expires_at' => now()->addMinutes(15)->toDateTimeString(),
                'hold_amount_minor' => $holdAmount,
                'total_price_minor' => $totalPrice,
                'cancellation_fee_minor' => 0,
                'idempotency_key' => $idempKey,
                'status' => 'HELD',
            ]);

            $claimRows = [];
            foreach ($slots as $slot) {
                $claimRows[] = [
                    'id' => (string) Str::uuid(),
                    'studio_id' => $studio->id,
                    'slot_start' => $slot['start'],
                    'slot_end' => $slot['end'],
                    'booking_id' => $booking->id,
                    'created_at' => now()->toDateTimeString(),
                    'updated_at' => now()->toDateTimeString(),
                ];
            }

            DB::table('med_studio_slot_claims')->insert($claimRows);

            return $booking;
        }, attempts: 3);
    }

    /**
     * Confirm a held booking.
     */
    public function confirmBooking(string $bookingId): MedStudioBooking
    {
        $booking = MedStudioBooking::findOrFail($bookingId);
        $booking->update(['status' => 'CONFIRMED']);

        return $booking->fresh();
    }

    /**
     * Check in client and capture booking payment.
     */
    public function checkInBooking(string $bookingId): MedStudioBooking
    {
        $booking = MedStudioBooking::findOrFail($bookingId);
        $booking->update(['status' => 'CHECKED_IN']);

        return $booking->fresh();
    }

    /**
     * Cancel booking and release slot claims back to inventory.
     */
    public function cancelBooking(string $bookingId, string $reason): MedStudioBooking
    {
        return DB::transaction(function () use ($bookingId) {
            $booking = MedStudioBooking::findOrFail($bookingId);

            // Release discrete slot claims
            MedStudioSlotClaim::where('booking_id', $booking->id)->delete();

            $booking->update(['status' => 'CANCELLED']);

            return $booking->fresh();
        });
    }

    /**
     * Mark no-show and apply contractual penalty.
     */
    public function markNoShow(string $bookingId): MedStudioBooking
    {
        $booking = MedStudioBooking::findOrFail($bookingId);
        $penalty = (int) round($booking->total_price_minor * 0.50); // 50% penalty fee

        $booking->update([
            'status' => 'NO_SHOW',
            'cancellation_fee_minor' => $penalty,
        ]);

        return $booking->fresh();
    }
}
