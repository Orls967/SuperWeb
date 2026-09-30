<?php

declare(strict_types=1);

namespace Modules\Mall\Application\Actions;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Mall\Domain\Enums\EventBookingStatus;
use Modules\Mall\Domain\Enums\EventType;
use Modules\Mall\Domain\Exceptions\EventScheduleConflictException;
use Modules\Mall\Domain\Models\EventBooking;
use Modules\Mall\Domain\Models\EventSpace;

class CreateEventBookingAction
{
    /**
     * Buat pemesanan sewa area atrium atau event space mall dengan deteksi bentrok jadwal.
     *
     * @throws EventScheduleConflictException
     * @throws InvalidArgumentException
     */
    public function execute(
        EventSpace $space,
        User $customer,
        string $eventName,
        EventType $eventType,
        Carbon|string $startDate,
        Carbon|string $endDate,
        int $boothCount = 0,
        ?int $tenantId = null,
        ?string $notes = null
    ): EventBooking {
        $startDate = $startDate instanceof Carbon ? $startDate : Carbon::parse($startDate);
        $endDate = $endDate instanceof Carbon ? $endDate : Carbon::parse($endDate);

        if ($endDate->lt($startDate)) {
            throw new InvalidArgumentException('Tanggal selesai tidak boleh sebelum tanggal mulai.');
        }

        // 1. Deteksi bentrok jadwal dengan booking yang sudah terkonfirmasi / aktif
        $conflict = EventBooking::query()
            ->where('event_space_id', $space->id)
            ->whereIn('status', [EventBookingStatus::CONFIRMED, EventBookingStatus::ONGOING])
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereDate('start_date', '<=', $endDate->toDateString())
                    ->whereDate('end_date', '>=', $startDate->toDateString());
            })
            ->exists();

        if ($conflict) {
            throw new EventScheduleConflictException("Jadwal event pada area {$space->name} bertabrakan dengan booking lain yang sudah terkonfirmasi.");
        }

        // 2. Hitung durasi hari dan total biaya sewa
        $days = max(1, (int) $startDate->diffInDays($endDate) + 1);
        $totalAmount = $days * $space->daily_rate;

        // Tambahan biaya booth jika bazaar
        if ($boothCount > 0) {
            $totalAmount += $boothCount * 250_000 * $days;
        }

        $bookingNumber = 'EVT-'.$startDate->format('Ymd').'-'.strtoupper(Str::random(4));

        return EventBooking::create([
            'property_id' => $space->property_id,
            'event_space_id' => $space->id,
            'customer_id' => $customer->id,
            'tenant_id' => $tenantId,
            'booking_number' => $bookingNumber,
            'event_name' => $eventName,
            'event_type' => $eventType,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'booth_count' => $boothCount,
            'total_amount' => $totalAmount,
            'paid_amount' => 0,
            'status' => EventBookingStatus::DRAFT,
            'notes' => $notes,
        ]);
    }
}
