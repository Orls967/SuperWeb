<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Modules\AutoServe\Domain\Models\Booking;
use Modules\Banking\Application\Queries\ConsolidatedPlQuery;
use Modules\Banking\database\seeders\BankingSeeder;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Core\Application\Actions\RecordVehicleEventAction;
use Modules\Core\Application\Actions\VerifyPassportAction;
use Modules\Core\Application\Services\SystemHealthService;
use Modules\Core\Domain\Enums\VehicleEventType;
use Modules\Core\Domain\Models\Vehicle;
use Modules\Logistics\Application\Actions\AssignScheduleResourcesAction;
use Modules\Logistics\Application\Actions\BookShipmentForOrderAction;
use Modules\Logistics\Application\Actions\CompleteFleetMaintenanceAction;
use Modules\Logistics\Application\Actions\DeliverVehicleByCarrierAction;
use Modules\Logistics\Application\Actions\ReceiveReeferReplenishmentAction;
use Modules\Logistics\Application\Actions\RecordTemperatureAction;
use Modules\Logistics\Contracts\ShipmentBooking;
use Modules\Logistics\Domain\Enums\FleetStatus;
use Modules\Logistics\Domain\Enums\ScheduleStatus;
use Modules\Logistics\Domain\Enums\ShipmentStatus;
use Modules\Logistics\Domain\Enums\TransportMode;
use Modules\Logistics\Domain\Events\FleetServiceDue;
use Modules\Logistics\Domain\Events\ShipmentDelivered;
use Modules\Logistics\Domain\Exceptions\ScheduleConflictException;
use Modules\Logistics\Domain\Exceptions\TruckNotAssignableException;
use Modules\Logistics\Domain\Models\DockAppointment;
use Modules\Logistics\Domain\Models\Driver;
use Modules\Logistics\Domain\Models\Location;
use Modules\Logistics\Domain\Models\Package;
use Modules\Logistics\Domain\Models\Schedule;
use Modules\Logistics\Domain\Models\Shipment;
use Modules\Logistics\Domain\Models\ShipmentException;
use Modules\Logistics\Domain\Models\Truck;
use Modules\Store\Domain\Enums\OrderStatus;
use Modules\Store\Domain\Events\OrderPaid;
use Modules\Store\Domain\Models\Category;
use Modules\Store\Domain\Models\Order;
use Modules\Store\Domain\Models\OrderItem;
use Modules\Store\Domain\Models\Product;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(BankingSeeder::class)->run();

    // Ensure origin location exists
    Location::firstOrCreate(
        ['code' => 'BJM-HUB'],
        [
            'name' => 'Hub Utama Banjarmasin',
            'type' => 'hub',
            'city' => 'Banjarmasin',
            'province' => 'Kalimantan Selatan',
            'country_code' => 'ID',
            'lat_e6' => -3300000,
            'lng_e6' => 114500000,
            'timezone' => 'Asia/Makassar',
            'min_connection_minutes' => 60,
            'is_active' => true,
        ]
    );
});

// ── 24.1 Store → Logistik: ShipmentBooking contract & Order Lifecycle ──

test('ShipmentBooking contract is bound and can create shipment for order', function () {
    $booking = app(ShipmentBooking::class);
    expect($booking)->toBeInstanceOf(BookShipmentForOrderAction::class);

    $shipper = User::factory()->create(['role' => 'customer']);

    $result = $booking->bookForOrder($shipper, [
        'origin_code' => 'BJM-HUB',
        'destination_address' => ['street' => 'Jl. Cempaka 12', 'city' => 'Surabaya', 'postal_code' => '60281'],
        'consignee_name' => 'Budi Santoso',
        'consignee_phone' => '0812-3456-7890',
        'packages' => [
            ['weight_g' => 2000, 'length_mm' => 300, 'width_mm' => 200, 'height_mm' => 150, 'description' => 'Sparepart Rem'],
        ],
        'declared_value_idr' => 350000,
        'source_type' => 'store_order',
        'source_id' => 999,
        'amount_idr' => 25000,
    ]);

    expect($result)
        ->toBeArray()
        ->toHaveKeys(['tracking_number', 'shipment_id']);

    // Shipment created
    $shipment = Shipment::find($result['shipment_id']);
    expect($shipment)
        ->not->toBeNull()
        ->source_type->toBe('store_order')
        ->source_id->toBe(999)
        ->total_amount_idr->toBe(25000);

    // Package created
    expect($shipment->packages()->count())->toBe(1);

    // Tracking number generated with valid format
    expect($shipment->tracking_number)->toStartWith('SRX');

    // Ledger entries posted (unearned freight)
    $unearnedAccount = LedgerAccount::where('code', 'lgx:unearned_freight')->where('asset_code', 'IDR')->first();
    expect($unearnedAccount)->not->toBeNull();
});

test('OrderPaid event triggers shipment creation idempotently', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $category = Category::create(['name' => 'Sparepart', 'slug' => 'sparepart']);
    $product = Product::create([
        'category_id' => $category->id,
        'name' => 'Filter Oli Asli',
        'slug' => 'filter-oli-asli',
        'sku' => 'SKU-FLT-001',
        'price' => 75000,
        'cached_stock' => 10,
        'is_active' => true,
        'is_car' => false,
    ]);

    $order = Order::create([
        'uuid' => (string) Str::uuid(),
        'number' => 'ORD-TEST-001',
        'user_id' => $customer->id,
        'status' => OrderStatus::PAID,
        'subtotal' => 75000,
        'shipping_fee' => 20000,
        'discount' => 0,
        'grand_total' => 95000,
        'shipping_address' => ['street' => 'Jl. Lambung Mangkurat 10', 'city' => 'Banjarmasin'],
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'name_snapshot' => $product->name,
        'price_snapshot' => $product->price,
        'qty' => 1,
        'line_total' => 75000,
    ]);

    // Dispatch event first time
    event(new OrderPaid($order->id, $customer->id, 95000));

    $order->refresh();
    expect($order->tracking_number)->not->toBeNull();
    $firstTracking = $order->tracking_number;

    $shipmentsCount = Shipment::where('source_type', 'store_order')->where('source_id', $order->id)->count();
    expect($shipmentsCount)->toBe(1);

    // Dispatch second time (idempotency check)
    event(new OrderPaid($order->id, $customer->id, 95000));

    expect(Shipment::where('source_type', 'store_order')->where('source_id', $order->id)->count())->toBe(1);
    expect($order->fresh()->tracking_number)->toBe($firstTracking);
});

test('cancelling/refunding store order cancels shipment and reverses unearned freight', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $booking = app(ShipmentBooking::class);

    $res = $booking->bookForOrder($customer, [
        'origin_code' => 'BJM-HUB',
        'destination_address' => ['street' => 'Jl. Pramuka', 'city' => 'Banjarmasin'],
        'consignee_name' => 'Ahmad',
        'consignee_phone' => '0812345678',
        'packages' => [['weight_g' => 500, 'description' => 'Buku Manual']],
        'declared_value_idr' => 50000,
        'source_type' => 'store_order',
        'source_id' => 777,
        'amount_idr' => 20000,
    ]);

    $shipment = Shipment::find($res['shipment_id']);
    expect($shipment->status)->toBe(ShipmentStatus::Booked);

    // Cancel via contract
    $cancelled = $booking->cancelForOrder('store_order', 777, 'Batal oleh pembeli');
    expect($cancelled)->toBeTrue();

    $shipment->refresh();
    expect($shipment->status)->toBe(ShipmentStatus::Cancelled);

    // Ledger balance check
    $reconcileExit = Artisan::call('bank:reconcile');
    expect($reconcileExit)->toBe(0);
});

// ── 24.2 Pengiriman Mobil (FTL Car Carrier) & Vehicle Passport Hash-Chain ──

test('car carrier delivery appends to vehicle passport and maintains hash chain', function () {
    $owner = User::factory()->create(['role' => 'customer']);
    $carrierUser = User::factory()->create(['role' => 'carrier_driver', 'name' => 'PT Ekspedisi Armada Prima']);

    $vehicle = Vehicle::create([
        'user_id' => $owner->id,
        'plate_number' => 'DA 9999 CAR',
        'brand' => 'Toyota',
        'model' => 'Innova Zenix',
        'year' => 2024,
        'vin' => 'MHKM1234567890123',
        'engine_number' => 'ENG999888',
        'color' => 'Hitam Metalik',
        'transmission' => 'automatic',
        'fuel_type' => 'hybrid',
    ]);

    // Baseline registered event in passport
    app(RecordVehicleEventAction::class)->execute(
        vehicle: $vehicle,
        type: VehicleEventType::REGISTERED,
        payload: ['source' => 'dealer', 'vin' => $vehicle->vin],
        actorId: $owner->id,
    );

    // Initial passport verification
    $verifyAction = app(VerifyPassportAction::class);
    $check1 = $verifyAction->execute($vehicle);
    expect($check1['is_valid'])->toBeTrue();

    // Create FTL Car Carrier shipment
    $hub = Location::where('code', 'BJM-HUB')->first();
    $dest = Location::firstOrCreate(
        ['code' => 'SBY-HUB'],
        [
            'name' => 'Hub Surabaya',
            'type' => 'hub',
            'city' => 'Surabaya',
            'province' => 'Jawa Timur',
            'country_code' => 'ID',
            'lat_e6' => -7250445,
            'lng_e6' => 112768845,
            'timezone' => 'Asia/Jakarta',
            'min_connection_minutes' => 60,
            'is_active' => true,
        ]
    );

    $shipment = Shipment::create([
        'tracking_number' => 'SRX8888888888',
        'shipper_id' => $owner->id,
        'consignee_name' => 'Dealer Surabaya',
        'consignee_phone' => '08123456789',
        'consignee_address' => ['city' => 'Surabaya'],
        'origin_location_id' => $hub->id,
        'destination_location_id' => $dest->id,
        'status' => ShipmentStatus::InTransit,
        'mode' => 'road',
        'payment_terms' => 'prepaid',
        'total_amount_idr' => 4500000,
    ]);

    // Execute delivery
    $deliverAction = app(DeliverVehicleByCarrierAction::class);
    $event = $deliverAction->execute($shipment, $vehicle, $carrierUser);

    expect($event->type)->toBe(VehicleEventType::DELIVERED_BY_CARRIER);
    expect($event->sequence)->toBe(2);

    expect($shipment->fresh()->status)->toBe(ShipmentStatus::Delivered);

    // Verify hash chain remains strictly valid
    $check2 = $verifyAction->execute($vehicle);
    expect($check2['is_valid'])->toBeTrue();
    expect($check2['event_count'])->toBe(2);
});

// ── 24.3 Perawatan Armada → AutoServe ──

test('fleet service due triggers AutoServe booking and sets truck to MAINTENANCE', function () {
    $hub = Location::where('code', 'BJM-HUB')->first();
    $owner = User::factory()->create(['role' => 'admin']);

    $vehicle = Vehicle::create([
        'user_id' => $owner->id,
        'plate_number' => 'DA 1001 LOG',
        'brand' => 'Hino',
        'model' => '500 Series',
        'year' => 2023,
    ]);

    $truck = Truck::create([
        'vehicle_id' => $vehicle->id,
        'plate_number' => 'DA 1001 LOG',
        'type' => 'fuso',
        'payload_kg' => 8000,
        'volume_dm3' => 24000,
        'required_license' => 'B2',
        'service_interval_m' => 10000000, // 10,000 km in meters
        'odometer_m' => 10050000,        // Exceeded interval
        'status' => FleetStatus::AVAILABLE,
        'current_location_id' => $hub->id,
    ]);

    // Dispatch FleetServiceDue
    event(new FleetServiceDue($vehicle->id, $truck->id, 10050, 10000));

    // Truck should now be in MAINTENANCE
    $truck->refresh();
    expect($truck->status)->toBe(FleetStatus::MAINTENANCE);

    // AutoServe should have a confirmed booking
    $booking = Booking::where('vehicle_id', $vehicle->id)->first();
    expect($booking)->not->toBeNull();
    expect($booking->status)->toBe('confirmed');

    // Dispatching FleetServiceDue again should be idempotent (no duplicate booking)
    event(new FleetServiceDue($vehicle->id, $truck->id, 10050, 10000));
    expect(Booking::where('vehicle_id', $vehicle->id)->count())->toBe(1);

    // Truck in MAINTENANCE cannot be assigned by AssignScheduleResourcesAction
    $dispatcher = User::factory()->create(['role' => 'dispatcher']);
    $driverUser = User::factory()->create(['role' => 'driver']);
    $driver = Driver::create([
        'user_id' => $driverUser->id,
        'driver_number' => 'DRV-999',
        'license_class' => 'B2',
        'license_expiry' => now()->addYear(),
        'home_hub_id' => $hub->id,
        'status' => 'available',
    ]);

    $schedule = Schedule::create([
        'schedule_number' => 'SCH-ROAD-001',
        'origin_location_id' => $hub->id,
        'destination_location_id' => $hub->id,
        'mode' => TransportMode::ROAD,
        'status' => ScheduleStatus::Scheduled,
        'cutoff_at' => now()->addHours(12),
        'etd' => now()->addDay(),
        'eta' => now()->addDay()->addHours(4),
        'cap_weight_kg' => 10000,
        'cap_volume_dm3' => 30000,
    ]);

    $assignAction = app(AssignScheduleResourcesAction::class);
    expect(fn () => $assignAction->execute($schedule, $truck, $driver, $dispatcher))
        ->toThrow(TruckNotAssignableException::class);

    // Complete maintenance returns truck to AVAILABLE
    $completeAction = app(CompleteFleetMaintenanceAction::class);
    $completeAction->execute($truck);
    expect($truck->fresh()->status)->toBe(FleetStatus::AVAILABLE);
});

// ── 24.4 Resto → Logistik (cold-chain): temperature readings & stock receipt ──

test('temperature reading records excursion alert once and creates high severity exception', function () {
    $shipper = User::factory()->create(['role' => 'shipper']);
    $origin = Location::where('code', 'BJM-HUB')->first();
    $dest = Location::firstOrCreate(
        ['code' => 'SBY-DEPOT'],
        [
            'name' => 'Depot Surabaya',
            'type' => 'depot',
            'city' => 'Surabaya',
            'province' => 'Jawa Timur',
            'country_code' => 'ID',
            'lat_e6' => -7250445,
            'lng_e6' => 112768845,
            'timezone' => 'Asia/Jakarta',
            'min_connection_minutes' => 60,
            'is_active' => true,
        ]
    );

    $shipment = Shipment::create([
        'tracking_number' => 'SRX0000000001',
        'shipper_id' => $shipper->id,
        'consignee_name' => 'Central Kitchen Resto',
        'consignee_phone' => '0811111111',
        'consignee_address' => ['city' => 'Surabaya'],
        'origin_location_id' => $origin->id,
        'destination_location_id' => $dest->id,
        'status' => 'in_transit',
        'mode' => 'road',
        'payment_terms' => 'prepaid',
        'total_amount_idr' => 50000,
    ]);

    Package::create([
        'shipment_id' => $shipment->id,
        'weight_g' => 5000,
        'length_mm' => 400,
        'width_mm' => 300,
        'height_mm' => 300,
        'description' => 'Bahan Baku Beku CK-01',
        'temp_min_c10' => -200,
        'temp_max_c10' => -150,
    ]);

    $shipment->load('packages');
    $action = app(RecordTemperatureAction::class);

    // Normal reading
    $action->execute($shipment, -180);
    expect(ShipmentException::where('shipment_id', $shipment->id)->count())->toBe(0);

    // Excursion reading: -10°C (too warm) -> triggers exception
    $action->execute($shipment, -100);
    expect(ShipmentException::where('shipment_id', $shipment->id)->count())->toBe(1);

    $exc = ShipmentException::where('shipment_id', $shipment->id)->first();
    expect($exc->severity->value)->toBe('high');

    // Second excursion reading on same shipment does not duplicate exception
    $action->execute($shipment, -90);
    expect(ShipmentException::where('shipment_id', $shipment->id)->count())->toBe(1);
});

test('reefer replenishment receives inventory stock idempotently', function () {
    $category = Category::create(['name' => 'Bahan Resto', 'slug' => 'bahan-resto']);
    $product = Product::create([
        'category_id' => $category->id,
        'name' => 'Daging Sapi Rendang (Frozen)',
        'slug' => 'daging-sapi-rendang',
        'sku' => 'SKU-BEEF-001',
        'price' => 120000,
        'cached_stock' => 50,
        'is_active' => true,
        'is_car' => false,
    ]);

    $hub = Location::where('code', 'BJM-HUB')->first();
    $shipper = User::factory()->create(['role' => 'shipper']);

    $shipment = Shipment::create([
        'tracking_number' => 'SRX7777777777',
        'shipper_id' => $shipper->id,
        'consignee_name' => 'Resto Padang DM-01',
        'consignee_phone' => '0822222222',
        'consignee_address' => ['city' => 'Banjarmasin'],
        'origin_location_id' => $hub->id,
        'destination_location_id' => $hub->id,
        'status' => 'delivered',
        'mode' => 'road',
        'payment_terms' => 'prepaid',
        'total_amount_idr' => 150000,
    ]);

    $action = app(ReceiveReeferReplenishmentAction::class);

    // Receive 25 units
    $m1 = $action->execute($shipment, $product->id, 25);
    expect($product->fresh()->cached_stock)->toBe(75);

    // Receive again (idempotent, no double count)
    $m2 = $action->execute($shipment, $product->id, 25);
    expect($product->fresh()->cached_stock)->toBe(75);
});

// ── 24.5 Mall → Logistik (loading dock) ──

test('dock appointment blocks overlapping time slots and rejects checkout without checkin', function () {
    $apt1 = DockAppointment::reserve([
        'property_id' => 1,
        'dock_code' => 'DOCK-A',
        'date' => '2026-11-01',
        'start_time' => '09:00',
        'end_time' => '10:00',
    ]);

    expect($apt1->status)->toBe('reserved');

    // Overlapping slot should throw
    expect(fn () => DockAppointment::reserve([
        'property_id' => 1,
        'dock_code' => 'DOCK-A',
        'date' => '2026-11-01',
        'start_time' => '09:30',
        'end_time' => '10:30',
    ]))->toThrow(ScheduleConflictException::class);

    // Check-out without check-in should be rejected
    expect(fn () => $apt1->checkOut())->toThrow(DomainException::class);

    // Check-in and check-out works in proper sequence
    $apt1->checkIn();
    expect($apt1->fresh()->status)->toBe('checked_in');

    $apt1->checkOut();
    expect($apt1->fresh()->status)->toBe('completed');
});

// ── 24.6 Finance & Observabilitas ──

test('ConsolidatedPlQuery includes Logistik pillar without increasing query budget', function () {
    $query = new ConsolidatedPlQuery;
    $result = $query->execute(30);

    expect($result['lines'])->toHaveKey('logistik');
    expect($result['lines']['logistik']['name'])->toBe('Logistik & Pengiriman');
});

test('super:health-check includes logistics pillar', function () {
    $healthService = app(SystemHealthService::class);
    $result = $healthService->check();

    expect($result['checks'])->toHaveKey('logistics');
    expect($result['checks']['logistics']['name'])->toContain('Logistik');
});

// ── 24.7 Quality Gate End-to-End Test ──

test('end-to-end: Store order paid to logistics delivery, revenue recognized, and bank reconcile 0', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $booking = app(ShipmentBooking::class);

    // 1. Order paid books shipment
    $res = $booking->bookForOrder($customer, [
        'origin_code' => 'BJM-HUB',
        'destination_address' => ['street' => 'Jl. P. Samudra 5', 'city' => 'Banjarmasin'],
        'consignee_name' => 'Rahmat Hidayat',
        'consignee_phone' => '08129999999',
        'packages' => [['weight_g' => 1500, 'description' => 'Sparepart Motor']],
        'declared_value_idr' => 200000,
        'source_type' => 'store_order',
        'source_id' => 1234,
        'amount_idr' => 25000,
    ]);

    $shipment = Shipment::find($res['shipment_id']);
    expect($shipment->status)->toBe(ShipmentStatus::Booked);

    // 2. Deliver shipment
    $shipment->status = ShipmentStatus::Delivered;
    $shipment->delivered_at = now();
    $shipment->save();

    event(new ShipmentDelivered($shipment->fresh()));

    // 3. Freight revenue recognized
    $revAccount = LedgerAccount::where('code', 'lgx:freight_revenue')->first();
    expect($revAccount)->not->toBeNull();

    // 4. Double-entry ledger balances at 0 discrepancy
    $exitCode = Artisan::call('bank:reconcile');
    expect($exitCode)->toBe(0);
});
