<?php

declare(strict_types=1);

namespace Modules\AutoServe\Application\Listeners;

use Carbon\Carbon;
use Illuminate\Support\Str;
use Modules\AutoServe\Domain\Enums\BookingStatus;
use Modules\AutoServe\Domain\Models\Booking;
use Modules\AutoServe\Domain\Models\Service;
use Modules\Core\Contracts\SimClockInterface;
use Modules\Core\Domain\Models\Vehicle;
use Modules\Telematics\Domain\Events\VehicleAnomalyDetected;

class CreatePredictiveMaintenanceBooking
{
    public function __construct(
        protected SimClockInterface $simClock
    ) {}

    public function handle(VehicleAnomalyDetected $event): void
    {
        $vehicle = Vehicle::find($event->vehicleId);
        if (! $vehicle) {
            return;
        }

        $bookingCode = 'PRED-'.strtoupper(Str::random(8));
        $date = Carbon::parse($this->simClock->now())->addDay()->format('Y-m-d');

        // Check if an existing open predictive booking already exists for this vehicle
        $existing = Booking::where('vehicle_id', $vehicle->id)
            ->whereIn('status', [BookingStatus::Pending, BookingStatus::Confirmed])
            ->where('complaint', 'like', '%[PREDICTIVE_MAINTENANCE]%')
            ->first();

        if ($existing) {
            return; // Idempotent 1x per open cycle
        }

        $service = Service::firstOrCreate(
            ['name' => 'General Inspection & Diagnostic'],
            [
                'description' => 'Standard diagnostic scan and engine inspection',
                'price' => 250000,
                'estimated_duration' => 60,
            ]
        );
        $serviceCost = $service->price;

        Booking::create([
            'booking_code' => $bookingCode,
            'customer_id' => $vehicle->user_id,
            'vehicle_id' => $vehicle->id,
            'service_id' => $service->id,
            'plate_number' => $vehicle->plate_number,
            'vehicle_brand' => $vehicle->brand ?? 'Toyota',
            'vehicle_model' => $vehicle->model ?? 'Innova',
            'vehicle_year' => $vehicle->year ?? 2022,
            'complaint' => "[PREDICTIVE_MAINTENANCE] Detected anomaly: {$event->anomalyType} (severity: {$event->severity})",
            'mechanic_notes' => 'Auto-generated draft booking by IoT Telematics anomaly detector.',
            'booking_date' => $date,
            'booking_time' => '09:00:00',
            'status' => BookingStatus::Pending,
            'service_cost' => $serviceCost,
            'sparepart_cost' => 0,
            'grand_total' => $serviceCost,
            'payment_status' => 'unpaid',
        ]);
    }
}
