<?php

declare(strict_types=1);

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Logistics\Application\Actions\RecordTrackingEventAction;
use Modules\Logistics\Domain\Enums\LocationType;
use Modules\Logistics\Domain\Enums\PaymentTerms;
use Modules\Logistics\Domain\Enums\ServiceLevel;
use Modules\Logistics\Domain\Enums\ShipmentStatus;
use Modules\Logistics\Domain\Models\Location;
use Modules\Logistics\Domain\Models\Shipment;
use Modules\Logistics\Domain\Models\TrackingEvent;
use Modules\Logistics\Domain\ValueObjects\TrackingNumber;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->origin = Location::create([
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

    $this->destination = Location::create([
        'code' => 'HUB-SUB',
        'name' => 'Surabaya Hub',
        'type' => LocationType::HUB,
        'city' => 'Surabaya',
        'province' => 'Jawa Timur',
        'country_code' => 'ID',
        'lat_e6' => -7257500,
        'lng_e6' => 112752100,
        'timezone' => 'Asia/Jakarta',
        'min_connection_minutes' => 60,
    ]);

    $this->shipperUser = User::factory()->create(['role' => 'shipper']);
    $this->operatorUser = User::factory()->create(['role' => 'hub_operator']);

    $this->shipment = Shipment::create([
        'tracking_number' => TrackingNumber::generate(),
        'shipper_id' => $this->shipperUser->id,
        'origin_location_id' => $this->origin->id,
        'destination_location_id' => $this->destination->id,
        'service_level' => ServiceLevel::Regular,
        'status' => ShipmentStatus::Booked,
        'payment_terms' => PaymentTerms::Prepaid,
        'chargeable_weight_kg' => '5.00',
        'rate_base_amount' => '50000.00',
        'rate_surcharge_amount' => '0.00',
        'rate_discount_amount' => '0.00',
        'rate_tax_amount' => '5500.00',
        'rate_total_amount' => '55500.00',
        'currency' => 'IDR',
        'consignee_name' => 'Budi Santoso',
        'consignee_phone' => '081234567890',
        'consignee_address' => 'Jl. Pahlawan No. 45, Surabaya',
        'consignee_postal_code' => '60174',
    ]);

    $this->action = new RecordTrackingEventAction;
});

test('tracking event is append-only and throws exception on update or delete attempt', function () {
    $event = $this->action->execute(
        shipment: $this->shipment,
        eventType: 'PICKED_UP',
        locationId: $this->origin->id,
        actor: $this->operatorUser,
        description: 'Shipment picked up at origin'
    );

    expect($event->id)->not->toBeNull();

    // Expect update to throw RuntimeException
    expect(fn () => $event->update(['description' => 'Tampered description']))
        ->toThrow(RuntimeException::class, 'TrackingEvent is append-only and immutable. Updates are strictly forbidden.');

    // Expect delete to throw RuntimeException
    expect(fn () => $event->delete())
        ->toThrow(RuntimeException::class, 'TrackingEvent is append-only and immutable. Deletions are strictly forbidden.');
});

test('sequential hash chain correctly links events with genesis and sha256 integrity', function () {
    $time1 = Carbon::parse('2026-10-01 10:00:00');
    $time2 = Carbon::parse('2026-10-01 11:30:00');
    $time3 = Carbon::parse('2026-10-01 14:00:00');

    $event1 = $this->action->execute(
        shipment: $this->shipment,
        eventType: 'BOOKED',
        locationId: $this->origin->id,
        actor: $this->shipperUser,
        description: 'Shipment booked via portal',
        payload: ['service' => 'STANDARD'],
        occurredAt: $time1
    );

    $genesisHash = TrackingEvent::genesisHash($this->shipment->id);
    expect($event1->sequence)->toBe(1)
        ->and($event1->prev_hash)->toBe($genesisHash)
        ->and($event1->hash)->toBe(
            TrackingEvent::calculateHash($genesisHash, 1, 'BOOKED', ['service' => 'STANDARD'], $time1)
        );

    $event2 = $this->action->execute(
        shipment: $this->shipment,
        eventType: 'HUB_INBOUND',
        locationId: $this->origin->id,
        actor: $this->operatorUser,
        description: 'Received at Jakarta Hub',
        payload: ['bay' => 'A1'],
        occurredAt: $time2
    );

    expect($event2->sequence)->toBe(2)
        ->and($event2->prev_hash)->toBe($event1->hash)
        ->and($event2->hash)->toBe(
            TrackingEvent::calculateHash($event1->hash, 2, 'HUB_INBOUND', ['bay' => 'A1'], $time2)
        );

    $event3 = $this->action->execute(
        shipment: $this->shipment,
        eventType: 'DEPARTED',
        locationId: $this->origin->id,
        actor: $this->operatorUser,
        description: 'Departed from Jakarta Hub',
        payload: ['vehicle' => 'B 1234 CD'],
        occurredAt: $time3
    );

    expect($event3->sequence)->toBe(3)
        ->and($event3->prev_hash)->toBe($event2->hash)
        ->and($event3->hash)->toBe(
            TrackingEvent::calculateHash($event2->hash, 3, 'DEPARTED', ['vehicle' => 'B 1234 CD'], $time3)
        );
});

test('verify custody command succeeds on uncorrupted event chain', function () {
    $this->action->execute($this->shipment, 'BOOKED', $this->origin->id, $this->shipperUser);
    $this->action->execute($this->shipment, 'HUB_INBOUND', $this->origin->id, $this->operatorUser);
    $this->action->execute($this->shipment, 'OUT_FOR_DELIVERY', $this->destination->id, $this->operatorUser);

    $this->artisan('lgx:verify-custody')
        ->expectsOutputToContain('Seluruh 1 pengiriman (3 event) rantai lacak balak terverifikasi valid dan bebas manipulasi.')
        ->assertExitCode(0);
});

test('verify custody command fails when event hash or payload has been tampered with', function () {
    $event1 = $this->action->execute($this->shipment, 'BOOKED', $this->origin->id, $this->shipperUser);
    $event2 = $this->action->execute($this->shipment, 'HUB_INBOUND', $this->origin->id, $this->operatorUser);

    // Bypass Eloquent model immutability with raw DB query to simulate database tampering
    DB::table('lgx_tracking_events')
        ->where('id', $event2->id)
        ->update(['payload' => json_encode(['tampered' => true])]);

    $this->artisan('lgx:verify-custody')
        ->expectsOutputToContain('Terdeteksi 1 pelanggaran integritas rantai lacak balak!')
        ->expectsOutputToContain('Hash SHA-256 tidak valid! Terdeteksi manipulasi data event.')
        ->assertExitCode(1);
});

test('verify custody command fails when event sequence is broken', function () {
    $this->action->execute($this->shipment, 'BOOKED', $this->origin->id, $this->shipperUser);
    $event2 = $this->action->execute($this->shipment, 'HUB_INBOUND', $this->origin->id, $this->operatorUser);

    DB::table('lgx_tracking_events')
        ->where('id', $event2->id)
        ->update(['sequence' => 99]);

    $this->artisan('lgx:verify-custody')
        ->expectsOutputToContain('Terdeteksi')
        ->expectsOutputToContain('Urutan sekuens terputus')
        ->assertExitCode(1);
});

test('shipment timeline integrates tracking events alongside legacy history', function () {
    $time = Carbon::parse('2026-10-01 10:00:00');

    $this->action->execute(
        shipment: $this->shipment,
        eventType: 'HUB_INBOUND',
        locationId: $this->origin->id,
        actor: $this->operatorUser,
        description: 'Tiba di fasilitas CGK',
        payload: ['manifest_id' => 'MNF-123'],
        occurredAt: $time
    );

    $timeline = $this->shipment->fresh()->getTimelineEvents();
    expect($timeline)->toBeArray()
        ->and(count($timeline))->toBeGreaterThanOrEqual(1);

    $hubEvent = collect($timeline)->firstWhere('status', 'HUB_INBOUND');
    expect($hubEvent)->not->toBeNull()
        ->and($hubEvent['description'])->toBe('Tiba di fasilitas CGK')
        ->and($hubEvent['location'])->toBe('Jakarta Central Hub')
        ->and($hubEvent['actor_role'])->toBe('hub_operator');
});
