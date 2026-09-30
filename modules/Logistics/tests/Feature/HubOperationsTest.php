<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Logistics\Domain\Enums\LocationType;
use Modules\Logistics\Domain\Enums\PaymentTerms;
use Modules\Logistics\Domain\Enums\ScheduleStatus;
use Modules\Logistics\Domain\Enums\ServiceLevel;
use Modules\Logistics\Domain\Enums\ShipmentStatus;
use Modules\Logistics\Domain\Enums\TransportMode;
use Modules\Logistics\Domain\Models\HubOperator;
use Modules\Logistics\Domain\Models\Location;
use Modules\Logistics\Domain\Models\Schedule;
use Modules\Logistics\Domain\Models\Shipment;
use Modules\Logistics\Domain\Models\ShipmentLeg;
use Modules\Logistics\Domain\Models\TrackingEvent;
use Modules\Logistics\Domain\ValueObjects\TrackingNumber;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Hub 1: Jakarta
    $this->hubJakarta = Location::create([
        'code' => 'HUB-CGK',
        'name' => 'Jakarta Central Hub',
        'type' => LocationType::HUB,
        'city' => 'Jakarta',
        'province' => 'DKI Jakarta',
        'country_code' => 'ID',
        'lat_e6' => -6200000,
        'lng_e6' => 106816666,
        'timezone' => 'Asia/Jakarta',
        'min_connection_minutes' => 60,
    ]);

    // Hub 2: Surabaya
    $this->hubSurabaya = Location::create([
        'code' => 'HUB-SUB',
        'name' => 'Surabaya Branch Hub',
        'type' => LocationType::HUB,
        'city' => 'Surabaya',
        'province' => 'Jawa Timur',
        'country_code' => 'ID',
        'lat_e6' => -7257500,
        'lng_e6' => 112752100,
        'timezone' => 'Asia/Jakarta',
        'min_connection_minutes' => 60,
    ]);

    // Hub 3: Banjarmasin (Outside route)
    $this->hubBanjarmasin = Location::create([
        'code' => 'HUB-BDJ',
        'name' => 'Banjarmasin Kalimantan Hub',
        'type' => LocationType::HUB,
        'city' => 'Banjarmasin',
        'province' => 'Kalimantan Selatan',
        'country_code' => 'ID',
        'lat_e6' => -3330000,
        'lng_e6' => 114570000,
        'timezone' => 'Asia/Makassar',
        'min_connection_minutes' => 60,
    ]);

    // Operators
    $this->jakartaOpUser = User::factory()->create(['role' => 'hub_operator', 'name' => 'Operator Jakarta']);
    HubOperator::create(['user_id' => $this->jakartaOpUser->id, 'hub_id' => $this->hubJakarta->id]);

    $this->banjarmasinOpUser = User::factory()->create(['role' => 'hub_operator', 'name' => 'Operator Banjarmasin']);
    HubOperator::create(['user_id' => $this->banjarmasinOpUser->id, 'hub_id' => $this->hubBanjarmasin->id]);

    $this->adminUser = User::factory()->create(['role' => 'logistics_admin', 'name' => 'Logistics Admin']);
    $this->shipperUser = User::factory()->create(['role' => 'shipper', 'name' => 'Shipper Corp']);

    // Shipment from Jakarta to Surabaya
    $this->shipment = Shipment::create([
        'tracking_number' => TrackingNumber::generate(),
        'shipper_id' => $this->shipperUser->id,
        'origin_location_id' => $this->hubJakarta->id,
        'destination_location_id' => $this->hubSurabaya->id,
        'service_level' => ServiceLevel::Regular,
        'status' => ShipmentStatus::Booked,
        'payment_terms' => PaymentTerms::Prepaid,
        'chargeable_weight_kg' => '10.00',
        'rate_base_amount' => '100000.00',
        'rate_surcharge_amount' => '0.00',
        'rate_discount_amount' => '0.00',
        'rate_tax_amount' => '11000.00',
        'rate_total_amount' => '111000.00',
        'currency' => 'IDR',
        'consignee_name' => 'Ahmad Santoso',
        'consignee_phone' => '081234567890',
        'consignee_address' => 'Jl. Pemuda No. 10, Surabaya',
        'consignee_postal_code' => '60271',
    ]);

    // Itinerary leg from Jakarta to Surabaya
    $this->leg = ShipmentLeg::create([
        'shipment_id' => $this->shipment->id,
        'leg_sequence' => 1,
        'mode' => TransportMode::ROAD,
        'origin_location_id' => $this->hubJakarta->id,
        'destination_location_id' => $this->hubSurabaya->id,
        'estimated_departure' => now()->addHours(2),
        'estimated_arrival' => now()->addHours(18),
        'status' => 'planned',
    ]);
});

test('hub operator only sees their own assigned hub and cannot access without assignment', function () {
    // Jakarta operator sees Jakarta hub
    $this->actingAs($this->jakartaOpUser)
        ->get(route('logistics.hub.index'))
        ->assertOk()
        ->assertSee('Operasi Fasilitas Hub: Jakarta Central Hub')
        ->assertSee('HUB-CGK');

    // Operator trying to pass another hub_id query string is still locked to Jakarta
    $this->actingAs($this->jakartaOpUser)
        ->get(route('logistics.hub.index', ['hub_id' => $this->hubBanjarmasin->id]))
        ->assertOk()
        ->assertSee('Operasi Fasilitas Hub: Jakarta Central Hub');

    // Admin can switch hubs
    $this->actingAs($this->adminUser)
        ->get(route('logistics.hub.index', ['hub_id' => $this->hubBanjarmasin->id]))
        ->assertOk()
        ->assertSee('Operasi Fasilitas Hub: Banjarmasin Kalimantan Hub')
        ->assertSee('HUB-BDJ');

    // Unassigned operator gets 403
    $unassigned = User::factory()->create(['role' => 'hub_operator']);
    $this->actingAs($unassigned)
        ->get(route('logistics.hub.index'))
        ->assertForbidden();

    // Unauthorized role gets 403
    $this->actingAs($this->shipperUser)
        ->get(route('logistics.hub.index'))
        ->assertForbidden();
});

test('inbound scan at valid route hub transitions shipment to AtHub and logs tracking event', function () {
    $this->actingAs($this->jakartaOpUser)
        ->post(route('logistics.hub.inbound'), [
            'tracking_number' => $this->shipment->tracking_number,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->shipment->refresh();
    expect($this->shipment->status)->toBe(ShipmentStatus::AtHub);

    $event = TrackingEvent::where('shipment_id', $this->shipment->id)->latest('sequence')->first();
    expect($event)->not->toBeNull()
        ->and($event->event_type)->toBe('HUB_INBOUND')
        ->and($event->location_id)->toBe($this->hubJakarta->id)
        ->and($event->actor_id)->toBe($this->jakartaOpUser->id)
        ->and($event->actor_role)->toBe('hub_operator');
});

test('inbound scan at hub outside itinerary automatically flags MISSORT and sets Exception status', function () {
    // Scanned in Banjarmasin, but shipment is strictly Jakarta -> Surabaya!
    $this->actingAs($this->banjarmasinOpUser)
        ->post(route('logistics.hub.inbound'), [
            'tracking_number' => $this->shipment->tracking_number,
        ])
        ->assertRedirect()
        ->assertSessionHas('warning');

    $this->shipment->refresh();
    expect($this->shipment->status)->toBe(ShipmentStatus::Exception);

    $event = TrackingEvent::where('shipment_id', $this->shipment->id)->latest('sequence')->first();
    expect($event)->not->toBeNull()
        ->and($event->event_type)->toBe('MISSORT')
        ->and($event->location_id)->toBe($this->hubBanjarmasin->id)
        ->and($event->payload['is_missort'])->toBeTrue();
});

test('sort scan records SORTED tracking event with bay information', function () {
    $this->actingAs($this->jakartaOpUser)
        ->post(route('logistics.hub.sort'), [
            'tracking_number' => $this->shipment->tracking_number,
            'sort_bay' => 'Bay 4 - Jalur Pantura',
            'next_location_id' => $this->hubSurabaya->id,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $event = TrackingEvent::where('shipment_id', $this->shipment->id)->latest('sequence')->first();
    expect($event)->not->toBeNull()
        ->and($event->event_type)->toBe('SORTED')
        ->and($event->payload['sort_bay'])->toBe('Bay 4 - Jalur Pantura')
        ->and($event->payload['next_location_id'])->toBe($this->hubSurabaya->id);
});

test('outbound scan loads shipment to schedule, transitions to InTransit, and verifies custody chain', function () {
    $schedule = Schedule::create([
        'schedule_number' => 'SCH-CGK-SUB-01',
        'mode' => TransportMode::ROAD,
        'origin_location_id' => $this->hubJakarta->id,
        'destination_location_id' => $this->hubSurabaya->id,
        'etd' => now()->addHours(1),
        'eta' => now()->addHours(15),
        'cutoff_at' => now()->addMinutes(30),
        'status' => ScheduleStatus::Scheduled,
        'cap_weight_kg' => '15000.00',
        'used_weight_kg' => '0.00',
        'cap_volume_dm3' => 45000,
        'used_volume_dm3' => 0,
    ]);

    // Outbound scan
    $this->actingAs($this->jakartaOpUser)
        ->post(route('logistics.hub.outbound'), [
            'tracking_number' => $this->shipment->tracking_number,
            'schedule_id' => $schedule->id,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->shipment->refresh();
    expect($this->shipment->status)->toBe(ShipmentStatus::InTransit);

    $event = TrackingEvent::where('shipment_id', $this->shipment->id)->latest('sequence')->first();
    expect($event)->not->toBeNull()
        ->and($event->event_type)->toBe('HUB_OUTBOUND')
        ->and($event->payload['schedule_number'])->toBe('SCH-CGK-SUB-01');

    // Cryptographic chain of custody passes verification
    $this->artisan('lgx:verify-custody', ['--shipment_id' => $this->shipment->id])
        ->assertExitCode(0);
});

test('outbound scan fails if schedule does not depart from this hub', function () {
    $scheduleWrongOrigin = Schedule::create([
        'schedule_number' => 'SCH-BDJ-SUB-02',
        'mode' => TransportMode::ROAD,
        'origin_location_id' => $this->hubBanjarmasin->id, // Depart from Banjarmasin, not Jakarta
        'destination_location_id' => $this->hubSurabaya->id,
        'etd' => now()->addHours(1),
        'eta' => now()->addHours(15),
        'cutoff_at' => now()->addMinutes(30),
        'status' => ScheduleStatus::Scheduled,
        'cap_weight_kg' => '15000.00',
        'used_weight_kg' => '0.00',
        'cap_volume_dm3' => 45000,
        'used_volume_dm3' => 0,
    ]);

    $this->actingAs($this->jakartaOpUser)
        ->post(route('logistics.hub.outbound'), [
            'tracking_number' => $this->shipment->tracking_number,
            'schedule_id' => $scheduleWrongOrigin->id,
        ])
        ->assertRedirect()
        ->assertSessionHas('error');
});
