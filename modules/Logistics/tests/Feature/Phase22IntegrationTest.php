<?php

declare(strict_types=1);

use App\Models\Brand;
use App\Models\Car;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Contracts\AcquiresVehicle;
use Modules\Logistics\Application\Actions\AssignScheduleResourcesAction;
use Modules\Logistics\Application\Actions\AssignShipmentToDriverAction;
use Modules\Logistics\Application\Actions\CompleteDeliveryAction;
use Modules\Logistics\Application\Actions\ProcessHubInboundScanAction;
use Modules\Logistics\Application\Actions\ProcessHubOutboundAction;
use Modules\Logistics\Application\Actions\ReserveCapacityAction;
use Modules\Logistics\Application\Actions\ScanPickupAction;
use Modules\Logistics\Application\Actions\StartDeliveryAction;
use Modules\Logistics\Domain\Enums\FleetStatus;
use Modules\Logistics\Domain\Enums\LocationType;
use Modules\Logistics\Domain\Enums\PaymentTerms;
use Modules\Logistics\Domain\Enums\ScheduleStatus;
use Modules\Logistics\Domain\Enums\ServiceLevel;
use Modules\Logistics\Domain\Enums\ShipmentStatus;
use Modules\Logistics\Domain\Enums\TransportMode;
use Modules\Logistics\Domain\Enums\TruckType;
use Modules\Logistics\Domain\Models\Driver;
use Modules\Logistics\Domain\Models\Location;
use Modules\Logistics\Domain\Models\Schedule;
use Modules\Logistics\Domain\Models\Shipment;
use Modules\Logistics\Domain\Models\TrackingEvent;
use Modules\Logistics\Domain\Models\Truck;
use Modules\Logistics\Domain\ValueObjects\TrackingNumber;

uses(RefreshDatabase::class);

test('phase 22 end to end: dispatch, capacity, hub flow, last mile delivery keep custody and capacity audits clean', function () {
    Storage::fake('local');

    $mk = fn (string $code, string $city) => Location::create([
        'code' => $code, 'name' => "Hub {$city}", 'type' => LocationType::HUB, 'city' => $city, 'province' => 'Kalimantan Selatan',
        'country_code' => 'ID', 'lat_e6' => -3300000, 'lng_e6' => 114500000, 'timezone' => 'Asia/Makassar', 'min_connection_minutes' => 60,
    ]);
    $hubA = $mk('HUB-BDJ', 'Banjarmasin');
    $hubB = $mk('HUB-BJB', 'Banjarbaru');

    $dispatcher = User::factory()->create(['role' => 'dispatcher']);
    $operatorA = User::factory()->create(['role' => 'hub_operator']);
    $operatorB = User::factory()->create(['role' => 'hub_operator']);
    $shipperUser = User::factory()->create(['role' => 'shipper']);

    $brand = Brand::firstOrCreate(['slug' => 'isuzu'], ['name' => 'Isuzu', 'country' => 'Japan', 'category' => 'other', 'is_active' => true]);
    $car = Car::firstOrCreate(
        ['slug' => 'isuzu-giga-fvr'],
        ['brand_id' => $brand->id, 'model' => 'Giga FVR', 'year_start' => 2022, 'body_type' => 'Truck', 'fuel_type' => 'diesel', 'price_idr' => 750000000, 'is_active' => true]
    );
    $vehicle = app(AcquiresVehicle::class)->handle(user: $dispatcher, car: $car, plateNumber: 'DA 9100 TA', color: 'White', vin: 'MHFVR34P0NK910001');
    $truck = Truck::create([
        'vehicle_id' => $vehicle->id, 'plate_number' => 'DA 9100 TA', 'type' => TruckType::CDD, 'payload_kg' => 5000, 'volume_dm3' => 14000,
        'required_license' => 'SIM B1 Umum', 'service_interval_m' => 10_000_000, 'odometer_m' => 0, 'status' => FleetStatus::AVAILABLE,
        'current_location_id' => $hubA->id,
    ]);
    $mkDriver = fn (string $no) => Driver::create([
        'user_id' => User::factory()->create(['role' => 'driver'])->id, 'driver_number' => $no, 'license_class' => 'SIM B1 Umum',
        'license_expiry' => now()->addYear(), 'home_hub_id' => $hubA->id, 'status' => 'available',
    ]);
    $linehaulDriver = $mkDriver('DRV-E2E-001');
    $pickupDriver = $mkDriver('DRV-E2E-002');
    $lastMileDriver = $mkDriver('DRV-E2E-003');

    $trip = Schedule::create([
        'schedule_number' => 'TRP-E2E-001', 'mode' => TransportMode::ROAD, 'origin_location_id' => $hubA->id, 'destination_location_id' => $hubB->id,
        'etd' => now()->addHours(3), 'eta' => now()->addHours(5), 'cutoff_at' => now()->addHour(), 'status' => ScheduleStatus::Scheduled,
        'cap_weight_kg' => '5000.000', 'cap_volume_dm3' => 14000,
    ]);
    $shipment = Shipment::create([
        'tracking_number' => TrackingNumber::generate(), 'shipper_id' => $shipperUser->id,
        'consignee_name' => 'Siti Penerima', 'consignee_phone' => '081200000000', 'consignee_address' => ['street' => 'Jl. Veteran 10', 'city' => 'Banjarbaru'],
        'origin_location_id' => $hubA->id, 'destination_location_id' => $hubB->id, 'service_level' => ServiceLevel::Regular,
        'mode' => TransportMode::ROAD, 'payment_terms' => PaymentTerms::Prepaid, 'status' => ShipmentStatus::Booked,
        'total_chargeable_weight_g' => 120000, 'total_amount_idr' => 90000, 'booked_at' => now(),
    ]);

    // 1. Dispatch: pickup driver for the shipment, linehaul truck + driver for the trip.
    app(AssignShipmentToDriverAction::class)->execute($shipment, $pickupDriver, $dispatcher);
    app(AssignScheduleResourcesAction::class)->execute($trip, $truck, $linehaulDriver, $dispatcher);
    expect($truck->fresh()->status)->toBe(FleetStatus::ASSIGNED);

    // 2. Pickup by driver, capacity reserved on the trip.
    app(ScanPickupAction::class)->execute($pickupDriver, $shipment->fresh());
    app(ReserveCapacityAction::class)->execute(scheduleId: $trip->id, weightKg: '120.000', volumeDm3: 500, idempotencyKey: 'e2e-'.$shipment->id, shipmentId: $shipment->id);

    // 3. Hub flow: inbound A -> outbound on trip -> inbound B.
    app(ProcessHubInboundScanAction::class)->execute($shipment->fresh(), $hubA, $operatorA);
    app(ProcessHubOutboundAction::class)->execute($shipment->fresh(), $hubA, $operatorA, $trip->fresh());
    $inboundB = app(ProcessHubInboundScanAction::class)->execute($shipment->fresh(), $hubB, $operatorB);
    expect($inboundB['is_missort'])->toBeFalse()->and($shipment->fresh()->status)->toBe(ShipmentStatus::AtHub);

    // 4. Last mile: assign, start (OTP), deliver with POD.
    app(AssignShipmentToDriverAction::class)->execute($shipment->fresh(), $lastMileDriver, $dispatcher);
    $otp = app(StartDeliveryAction::class)->execute($lastMileDriver, $shipment->fresh())['otp'];
    $png = imagecreatetruecolor(200, 80);
    ob_start();
    imagepng($png);
    $signature = 'data:image/png;base64,'.base64_encode((string) ob_get_clean());
    app(CompleteDeliveryAction::class)->execute($lastMileDriver, $shipment->fresh(), 'Siti Penerima', $otp, UploadedFile::fake()->image('pod.jpg'), $signature);

    expect($shipment->fresh()->status)->toBe(ShipmentStatus::Delivered);
    $types = TrackingEvent::where('shipment_id', $shipment->id)->orderBy('sequence')->pluck('event_type')->all();
    expect($types)->toBe([
        'DRIVER_ASSIGNED', 'PICKED_UP', 'HUB_INBOUND', 'HUB_OUTBOUND', 'HUB_INBOUND', 'DRIVER_ASSIGNED', 'OUT_FOR_DELIVERY', 'DELIVERED',
    ]);

    // 5. Phase gate audits run against non-empty data and pass.
    $this->artisan('lgx:verify-custody')->expectsOutputToContain('1 pengiriman (8 event)')->assertSuccessful();
    $this->artisan('lgx:capacity-check')->assertSuccessful();
    $this->artisan('lgx:detect-late')->expectsOutputToContain('baru: 0')->assertSuccessful();
});
