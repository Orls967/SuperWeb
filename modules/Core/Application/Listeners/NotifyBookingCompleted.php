<?php

declare(strict_types=1);

namespace Modules\Core\Application\Listeners;

use Modules\AutoServe\Domain\Events\BookingCompleted;
use Modules\Core\Application\Services\ActivityLogger;
use Modules\Core\Application\Services\NotificationService;

class NotifyBookingCompleted
{
    public function __construct(
        private NotificationService $notifications,
        private ActivityLogger $activity,
    ) {}

    public function handle(BookingCompleted $event): void
    {
        $booking = $event->booking;
        $customerId = $booking->customer_id;

        // Notify the customer
        $this->notifications->send(
            userId: $customerId,
            type: 'booking_completed',
            title: 'Servis Selesai! 🎉',
            body: "Servis {$booking->service->name} untuk {$booking->vehicle_brand} {$booking->vehicle_model} ({$booking->plate_number}) telah selesai.",
            icon: 'success',
            actionUrl: route('bookings.show', $booking),
            actionLabel: 'Lihat Detail',
            meta: [
                'booking_id' => $booking->id,
                'booking_code' => $booking->booking_code,
            ],
        );

        // Log activity
        $this->activity->log(
            module: 'autoserve',
            event: 'booking_completed',
            description: "Servis {$booking->booking_code} selesai — {$booking->service->name}",
            userId: $customerId,
            subject: $booking,
            properties: [
                'booking_code' => $booking->booking_code,
                'service' => $booking->service->name,
                'grand_total' => $booking->grand_total,
            ],
        );
    }
}
