<?php

declare(strict_types=1);

namespace Modules\AutoServe\Application\Services;

use Illuminate\Support\Str;
use Modules\AutoServe\Domain\Enums\BookingStatus;
use Modules\AutoServe\Domain\Models\Booking;
use Modules\AutoServe\Domain\Models\Service;
use Modules\Core\Domain\Models\Vehicle;
use Modules\Logistics\Contracts\FleetMaintenanceBooking;

class AutoServeFleetMaintenanceBooking implements FleetMaintenanceBooking
{
    /**
     * {@inheritDoc}
     */
    public function bookFleetService(int $vehicleId, int $odometerKm, string $reason): array
    {
        // Prevent double booking for the same vehicle while a service is in progress
        $existing = Booking::where('vehicle_id', $vehicleId)
            ->whereIn('status', [
                BookingStatus::Pending,
                BookingStatus::Confirmed,
                BookingStatus::InProgress,
                BookingStatus::WaitingParts,
                BookingStatus::AwaitingExtraApproval,
            ])
            ->first();

        if ($existing) {
            return ['booking_id' => $existing->id];
        }

        $vehicle = Vehicle::find($vehicleId);

        $service = Service::firstOrCreate(
            ['name' => 'Perawatan Berkala Armada'],
            [
                'description' => 'Servis dan inspeksi berkala armada logistik',
                'price' => 500000,
                'is_active' => true,
            ]
        );

        $booking = Booking::create([
            'booking_code' => 'SRV-FLT-'.strtoupper(Str::random(6)),
            'customer_id' => $vehicle?->user_id ?? 1,
            'vehicle_id' => $vehicleId,
            'service_id' => $service->id,
            'plate_number' => $vehicle?->plate_number ?? 'ARMADA-LOGISTIK',
            'vehicle_brand' => $vehicle?->brand ?? 'Truck',
            'vehicle_model' => $vehicle?->model ?? 'Hino 500',
            'vehicle_year' => $vehicle?->year ?? 2024,
            'complaint' => "{$reason} (Odometer: {$odometerKm} km)",
            'booking_date' => now()->toDateString(),
            'booking_time' => now()->format('H:i'),
            'status' => BookingStatus::Confirmed,
            'service_cost' => 500000,
            'sparepart_cost' => 0,
            'grand_total' => 500000,
        ]);

        return ['booking_id' => $booking->id];
    }
}
