<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Domain\Models\PlatformNotification;
use Modules\Logistics\Application\Actions\CompleteDeliveryAction;
use Modules\Logistics\Application\Actions\ReportFailedDeliveryAction;
use Modules\Logistics\Application\Actions\ScanPickupAction;
use Modules\Logistics\Application\Actions\StartDeliveryAction;
use Modules\Logistics\Domain\Enums\DeliveryFailureReason;
use Modules\Logistics\Domain\Enums\LocationType;
use Modules\Logistics\Domain\Enums\PaymentTerms;
use Modules\Logistics\Domain\Enums\ServiceLevel;
use Modules\Logistics\Domain\Enums\ShipmentStatus;
use Modules\Logistics\Domain\Enums\TransportMode;
use Modules\Logistics\Domain\Exceptions\InvalidDeliveryOperationException;
use Modules\Logistics\Domain\Exceptions\InvalidDeliveryOtpException;
use Modules\Logistics\Domain\Models\Driver;
use Modules\Logistics\Domain\Models\Location;
use Modules\Logistics\Domain\Models\ProofOfDelivery;
use Modules\Logistics\Domain\Models\Shipment;
use Modules\Logistics\Domain\Models\TrackingEvent;
use Modules\Logistics\Domain\ValueObjects\TrackingNumber;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    RateLimiter::clear('lgx-otp:1');

    $mk = fn (string $code, string $city) => Location::create([
        'code' => $code, 'name' => "Hub {$city}", 'type' => LocationType::HUB, 'city' => $city, 'province' => 'Kalimantan Selatan',
        'country_code' => 'ID', 'lat_e6' => -3300000, 'lng_e6' => 114500000, 'timezone' => 'Asia/Makassar', 'min_connection_minutes' => 60,
    ]);
    $this->hubA = $mk('HUB-BDJ', 'Banjarmasin');
    $this->hubB = $mk('HUB-BJB', 'Banjarbaru');

    $this->driverUser = User::factory()->create(['role' => 'driver', 'name' => 'Budi Driver']);
    $this->driver = Driver::create([
        'user_id' => $this->driverUser->id, 'driver_number' => 'DRV-APP-001', 'license_class' => 'SIM B1 Umum',
        'license_expiry' => now()->addYear(), 'home_hub_id' => $this->hubA->id, 'status' => 'available',
    ]);
    $this->shipperUser = User::factory()->create(['role' => 'shipper']);
});

function makeStop(object $t, ShipmentStatus $status, ?Driver $driver = null): Shipment
{
    return Shipment::create([
        'tracking_number' => TrackingNumber::generate(), 'shipper_id' => $t->shipperUser->id,
        'consignee_name' => 'Siti Penerima', 'consignee_phone' => '081200000000',
        'consignee_address' => ['street' => 'Jl. Veteran 10', 'city' => 'Banjarbaru'],
        'origin_location_id' => $t->hubA->id, 'destination_location_id' => $t->hubB->id,
        'service_level' => ServiceLevel::Regular, 'mode' => TransportMode::ROAD, 'payment_terms' => PaymentTerms::Prepaid,
        'status' => $status, 'total_chargeable_weight_g' => 1000, 'total_amount_idr' => 50000, 'booked_at' => now(),
        'driver_id' => ($driver ?? $t->driver)->id,
    ]);
}

function signatureDataUrl(): string
{
    $img = imagecreatetruecolor(300, 110);
    $white = imagecolorallocate($img, 255, 255, 255);
    imagefill($img, 0, 0, $white);
    $ink = imagecolorallocate($img, 10, 10, 10);
    for ($i = 0; $i < 300; $i += 7) {
        imageline($img, $i, 20 + ($i * 13) % 70, $i + 5, 90 - ($i * 7) % 60, $ink);
    }
    ob_start();
    imagepng($img);

    return 'data:image/png;base64,'.base64_encode((string) ob_get_clean());
}

function otpFor(Shipment $shipment): string
{
    return app(StartDeliveryAction::class)->execute($shipment->driver, $shipment)['otp'];
}

function deliverPayload(string $otp, array $overrides = []): array
{
    return array_merge([
        'receiver_name' => 'Siti Penerima', 'otp' => $otp,
        'photo' => UploadedFile::fake()->image('pod.jpg', 400, 300), 'signature' => signatureDataUrl(),
    ], $overrides);
}

test('driver scans pickup and the custody chain records it', function () {
    $shipment = makeStop($this, ShipmentStatus::Booked);

    app(ScanPickupAction::class)->execute($this->driver, $shipment);

    expect($shipment->fresh()->status)->toBe(ShipmentStatus::PickedUp)
        ->and($shipment->fresh()->picked_up_at)->not->toBeNull()
        ->and(TrackingEvent::where('shipment_id', $shipment->id)->value('event_type'))->toBe('PICKED_UP');
});

test('driver cannot act on a shipment assigned to another driver', function () {
    $other = Driver::create([
        'user_id' => User::factory()->create(['role' => 'driver'])->id, 'driver_number' => 'DRV-APP-002', 'license_class' => 'SIM B1 Umum',
        'license_expiry' => now()->addYear(), 'home_hub_id' => $this->hubA->id, 'status' => 'available',
    ]);
    $shipment = makeStop($this, ShipmentStatus::Booked, $other);

    expect(fn () => app(ScanPickupAction::class)->execute($this->driver, $shipment))
        ->toThrow(InvalidDeliveryOperationException::class, 'tidak ditugaskan');
    expect($shipment->fresh()->status)->toBe(ShipmentStatus::Booked);
});

test('start delivery stores only a hash of the six digit otp and notifies the shipper', function () {
    $shipment = makeStop($this, ShipmentStatus::AtHub);

    $result = app(StartDeliveryAction::class)->execute($this->driver, $shipment);

    $fresh = $shipment->fresh();
    expect($result['otp'])->toMatch('/^\d{6}$/')
        ->and($fresh->status)->toBe(ShipmentStatus::OutForDelivery)
        ->and($fresh->delivery_otp_hash)->not->toBe($result['otp'])
        ->and(Hash::check($result['otp'], $fresh->delivery_otp_hash))->toBeTrue()
        ->and($fresh->toArray())->not->toHaveKey('delivery_otp_hash')
        ->and(PlatformNotification::where('user_id', $this->shipperUser->id)->where('type', 'logistics.delivery_otp')->value('body'))->toContain($result['otp']);

    // OTP tidak boleh bocor ke rantai kustodi (terlihat di pelacakan).
    foreach (TrackingEvent::where('shipment_id', $shipment->id)->get() as $event) {
        expect(json_encode($event->payload).$event->description)->not->toContain($result['otp']);
    }
});

test('delivery cannot start from a status other than at hub', function () {
    expect(fn () => app(StartDeliveryAction::class)->execute($this->driver, makeStop($this, ShipmentStatus::Booked)))
        ->toThrow(InvalidDeliveryOperationException::class);
});

test('proof of delivery completes the shipment with photo signature and verified otp', function () {
    $shipment = makeStop($this, ShipmentStatus::AtHub);
    $otp = otpFor($shipment);
    $photo = UploadedFile::fake()->image('pod.jpg', 400, 300);

    $pod = app(CompleteDeliveryAction::class)->execute($this->driver, $shipment, 'Siti Penerima', $otp, $photo, signatureDataUrl());

    $fresh = $shipment->fresh();
    expect($fresh->status)->toBe(ShipmentStatus::Delivered)
        ->and($fresh->delivered_at)->not->toBeNull()
        ->and($fresh->delivery_otp_hash)->toBeNull()
        ->and($pod->receiver_name)->toBe('Siti Penerima')
        ->and($pod->otp_verified)->toBeTrue();
    Storage::disk('local')->assertExists([$pod->photo_path, $pod->signature_path]);
    expect(substr(Storage::disk('local')->get($pod->signature_path), 1, 3))->toBe('PNG')
        ->and(TrackingEvent::where('shipment_id', $shipment->id)->orderByDesc('sequence')->value('event_type'))->toBe('DELIVERED');
});

test('wrong otp is rejected, nothing is stored and repeated guessing is locked out', function () {
    $shipment = makeStop($this, ShipmentStatus::AtHub);
    otpFor($shipment);
    RateLimiter::clear('lgx-otp:'.$shipment->id);
    $action = app(CompleteDeliveryAction::class);

    for ($i = 0; $i < CompleteDeliveryAction::MAX_OTP_ATTEMPTS; $i++) {
        expect(fn () => $action->execute($this->driver, $shipment, 'X', '000000', UploadedFile::fake()->image('p.jpg'), signatureDataUrl()))
            ->toThrow(InvalidDeliveryOtpException::class, 'salah');
    }

    expect(fn () => $action->execute($this->driver, $shipment, 'X', '000000', UploadedFile::fake()->image('p.jpg'), signatureDataUrl()))
        ->toThrow(InvalidDeliveryOtpException::class, 'Terlalu banyak');
    expect($shipment->fresh()->status)->toBe(ShipmentStatus::OutForDelivery)
        ->and(ProofOfDelivery::count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

test('invalid signature payloads are rejected', function () {
    $shipment = makeStop($this, ShipmentStatus::AtHub);
    $otp = otpFor($shipment);
    $action = app(CompleteDeliveryAction::class);

    foreach (['not-a-data-url', 'data:image/png;base64,'.base64_encode('<script>alert(1)</script>'.str_repeat('x', 200)), 'data:image/svg+xml;base64,PHN2Zz4='] as $bad) {
        expect(fn () => $action->execute($this->driver, $shipment, 'Siti', $otp, UploadedFile::fake()->image('p.jpg'), $bad))
            ->toThrow(InvalidDeliveryOperationException::class);
    }
    expect($shipment->fresh()->status)->toBe(ShipmentStatus::OutForDelivery);
});

test('three failed delivery attempts automatically return the shipment to sender', function () {
    $shipment = makeStop($this, ShipmentStatus::AtHub);
    otpFor($shipment);
    $action = app(ReportFailedDeliveryAction::class);

    $first = $action->execute($this->driver, $shipment, DeliveryFailureReason::ConsigneeUnavailable);
    $second = $action->execute($this->driver, $shipment, DeliveryFailureReason::AddressNotFound, 'Rumah kosong');

    expect($first['returned'])->toBeFalse()
        ->and($second['returned'])->toBeFalse()
        ->and($shipment->fresh()->status)->toBe(ShipmentStatus::OutForDelivery);

    $third = $action->execute($this->driver, $shipment, DeliveryFailureReason::Refused);

    expect($third['returned'])->toBeTrue()
        ->and($shipment->fresh()->status)->toBe(ShipmentStatus::ReturnToSender)
        ->and($shipment->fresh()->failed_delivery_attempts)->toBe(3)
        ->and($shipment->fresh()->delivery_otp_hash)->toBeNull()
        ->and($shipment->deliveryAttempts()->count())->toBe(3)
        ->and(TrackingEvent::where('shipment_id', $shipment->id)->where('event_type', 'DELIVERY_FAILED')->count())->toBe(3)
        ->and(TrackingEvent::where('shipment_id', $shipment->id)->orderByDesc('sequence')->value('event_type'))->toBe('RETURN_TO_SENDER');

    expect(fn () => $action->execute($this->driver, $shipment, DeliveryFailureReason::Other))
        ->toThrow(InvalidDeliveryOperationException::class);
});

test('a successful delivery after a failed attempt still works', function () {
    $shipment = makeStop($this, ShipmentStatus::AtHub);
    otpFor($shipment);
    app(ReportFailedDeliveryAction::class)->execute($this->driver, $shipment, DeliveryFailureReason::ConsigneeUnavailable);
    $newOtp = app(StartDeliveryAction::class)->execute($this->driver, $shipment->fresh())['otp']; // kirim ulang OTP

    app(CompleteDeliveryAction::class)->execute($this->driver, $shipment, 'Siti', $newOtp, UploadedFile::fake()->image('p.jpg'), signatureDataUrl());

    expect($shipment->fresh()->status)->toBe(ShipmentStatus::Delivered)
        ->and(TrackingEvent::where('shipment_id', $shipment->id)->where('event_type', 'OUT_FOR_DELIVERY')->count())->toBe(1);
});

test('tracking chain stays valid after the whole last mile flow', function () {
    $shipment = makeStop($this, ShipmentStatus::Booked);
    app(ScanPickupAction::class)->execute($this->driver, $shipment);
    $shipment->update(['status' => ShipmentStatus::AtHub]);
    $otp = otpFor($shipment->fresh());
    app(CompleteDeliveryAction::class)->execute($this->driver, $shipment, 'Siti', $otp, UploadedFile::fake()->image('p.jpg'), signatureDataUrl());

    $this->artisan('lgx:verify-custody')->assertSuccessful();
});

test('driver app http flow works end to end for the driver only', function () {
    $pickup = makeStop($this, ShipmentStatus::Booked);
    $atHub = makeStop($this, ShipmentStatus::AtHub);

    $this->actingAs($this->driverUser)->get(route('logistics.driver.tasks'))
        ->assertOk()->assertSee($pickup->tracking_number)->assertSee($atHub->tracking_number)->assertSee('Mulai Antar');

    $this->actingAs($this->driverUser)->post(route('logistics.driver.pickup'), ['tracking_number' => $pickup->tracking_number])
        ->assertSessionHas('success');
    expect($pickup->fresh()->status)->toBe(ShipmentStatus::PickedUp);

    $this->actingAs($this->driverUser)->post(route('logistics.driver.start-delivery', $atHub->id))->assertSessionHas('success');
    $otp = null;
    $body = PlatformNotification::where('type', 'logistics.delivery_otp')->latest('id')->value('body');
    preg_match('/\d{6}/', $body, $m);
    $otp = $m[0];

    $this->actingAs($this->driverUser)->post(route('logistics.driver.deliver', $atHub->id), deliverPayload($otp, ['otp' => '12345']))
        ->assertSessionHasErrors('otp');

    $this->actingAs($this->driverUser)->post(route('logistics.driver.deliver', $atHub->id), deliverPayload($otp))
        ->assertSessionHas('success');
    expect($atHub->fresh()->status)->toBe(ShipmentStatus::Delivered);

    foreach (['shipper', 'hub_operator', 'dispatcher', 'customer'] as $role) {
        $this->actingAs(User::factory()->create(['role' => $role]))->get(route('logistics.driver.tasks'))->assertForbidden();
    }
    $this->actingAs(User::factory()->create(['role' => 'driver']))->get(route('logistics.driver.tasks'))->assertForbidden(); // tanpa profil Driver
});

test('driver cannot deliver or fail a shipment belonging to someone else through http', function () {
    $other = Driver::create([
        'user_id' => User::factory()->create(['role' => 'driver'])->id, 'driver_number' => 'DRV-APP-009', 'license_class' => 'SIM B1 Umum',
        'license_expiry' => now()->addYear(), 'home_hub_id' => $this->hubA->id, 'status' => 'available',
    ]);
    $shipment = makeStop($this, ShipmentStatus::AtHub, $other);

    $this->actingAs($this->driverUser)->post(route('logistics.driver.start-delivery', $shipment->id))->assertSessionHas('error');
    $this->actingAs($this->driverUser)->post(route('logistics.driver.fail', $shipment->id), ['reason' => 'refused'])->assertSessionHas('error');
    expect($shipment->fresh()->status)->toBe(ShipmentStatus::AtHub);
});

test('failed delivery through http reports return to sender on the third attempt', function () {
    $shipment = makeStop($this, ShipmentStatus::AtHub);
    $this->actingAs($this->driverUser)->post(route('logistics.driver.start-delivery', $shipment->id));

    foreach (['consignee_unavailable', 'address_not_found'] as $reason) {
        $this->actingAs($this->driverUser)->post(route('logistics.driver.fail', $shipment->id), ['reason' => $reason])->assertSessionHas('success');
    }
    $this->actingAs($this->driverUser)->post(route('logistics.driver.fail', $shipment->id), ['reason' => 'refused'])->assertSessionHas('warning');

    expect($shipment->fresh()->status)->toBe(ShipmentStatus::ReturnToSender);
});
