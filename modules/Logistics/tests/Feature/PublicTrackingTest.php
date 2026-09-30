<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Logistics\Domain\Enums\LocationType;
use Modules\Logistics\Domain\Enums\PaymentTerms;
use Modules\Logistics\Domain\Enums\ServiceLevel;
use Modules\Logistics\Domain\Enums\ShipmentStatus;
use Modules\Logistics\Domain\Enums\TransportMode;
use Modules\Logistics\Domain\Models\Location;
use Modules\Logistics\Domain\Models\Package;
use Modules\Logistics\Domain\Models\Shipment;
use Modules\Logistics\Domain\Services\PiiMasker;
use Modules\Logistics\Domain\ValueObjects\TrackingNumber;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->origin = Location::create([
        'code' => 'HUB-BDJ',
        'name' => 'Hub Utama Banjarmasin',
        'type' => LocationType::HUB,
        'city' => 'Banjarmasin',
        'province' => 'Kalimantan Selatan',
        'country_code' => 'ID',
        'lat_e6' => -3319400,
        'lng_e6' => 114590800,
        'timezone' => 'Asia/Makassar',
        'min_connection_minutes' => 60,
        'is_active' => true,
    ]);

    $this->destination = Location::create([
        'code' => 'HUB-BJB',
        'name' => 'Hub Banjarbaru',
        'type' => LocationType::HUB,
        'city' => 'Banjarbaru',
        'province' => 'Kalimantan Selatan',
        'country_code' => 'ID',
        'lat_e6' => -3440900,
        'lng_e6' => 114830400,
        'timezone' => 'Asia/Makassar',
        'min_connection_minutes' => 60,
        'is_active' => true,
    ]);

    $this->shipper = User::factory()->create(['name' => 'PT Sumber Rezeki']);

    $this->shipment = Shipment::create([
        'tracking_number' => TrackingNumber::generate(),
        'shipper_id' => $this->shipper->id,
        'consignee_name' => 'Budi Santoso',
        'consignee_phone' => '081234567890',
        'consignee_address' => [
            'street' => 'Jl. Ahmad Yani Km 34 No. 56',
            'city' => 'Banjarbaru',
            'postal_code' => '70714',
        ],
        'origin_location_id' => $this->origin->id,
        'destination_location_id' => $this->destination->id,
        'service_level' => ServiceLevel::Regular,
        'mode' => TransportMode::ROAD,
        'payment_terms' => PaymentTerms::Prepaid,
        'status' => ShipmentStatus::Booked,
        'total_chargeable_weight_g' => 3500,
        'total_amount_idr' => 45000,
        'booked_at' => now()->subHours(2),
    ]);

    Package::create([
        'shipment_id' => $this->shipment->id,
        'weight_g' => 3500,
        'length_mm' => 300,
        'width_mm' => 200,
        'height_mm' => 150,
        'description' => 'Paket Suku Cadang Mesin',
    ]);
});

test('public tracking search page is accessible without login', function () {
    $response = $this->get(route('track.index'));

    $response->assertOk()
        ->assertSee('Lacak Pengiriman Kargo')
        ->assertSee('Masukkan Nomor Resi');
});

test('submitting search query redirects to tracking show route', function () {
    $response = $this->get(route('track.index', ['q' => $this->shipment->tracking_number]));

    $response->assertRedirect(route('track.show', ['tracking_number' => $this->shipment->tracking_number]));
});

test('public can track shipment without login with masked consignee PII', function () {
    $response = $this->get(route('track.show', ['tracking_number' => $this->shipment->tracking_number]));

    $response->assertOk()
        ->assertSee($this->shipment->tracking_number)
        ->assertSee('Hub Utama Banjarmasin')
        ->assertSee('Hub Banjarbaru')
        // Masked name: B*** S***
        ->assertSee('B*** S***')
        ->assertDontSee('Budi Santoso')
        // Masked phone: 0812****7890
        ->assertSee('0812****7890')
        ->assertDontSee('081234567890')
        // Masked street: contains disamarkan and not full street address
        ->assertSee('Disamarkan untuk privasi')
        ->assertDontSee('Jl. Ahmad Yani Km 34 No. 56')
        // Timeline event
        ->assertSee('Pesanan Dikonfirmasi');
});

test('tracking non-existent shipment displays not found view', function () {
    $response = $this->get(route('track.show', ['tracking_number' => 'SRX9999999999']));

    $response->assertOk()
        ->assertSee('Nomor Resi Tidak Ditemukan')
        ->assertSee('SRX9999999999');
});

test('pii masker masks names, phones, and addresses correctly', function () {
    $masker = new PiiMasker;

    expect($masker->maskName('Budi Santoso'))->toBe('B*** S***')
        ->and($masker->maskName('Ahmad'))->toBe('A***')
        ->and($masker->maskName(''))->toBe('***')
        ->and($masker->maskName(null))->toBe('***');

    expect($masker->maskPhone('081234567890'))->toBe('0812****7890')
        ->and($masker->maskPhone('+6281234567890'))->toBe('+628****7890')
        ->and($masker->maskPhone('12345'))->toBe('12****')
        ->and($masker->maskPhone(null))->toBe('****');

    $masked = $masker->maskAddress([
        'street' => 'Jl. Lambung Mangkurat No. 88',
        'city' => 'Banjarmasin',
        'postal_code' => '70111',
    ]);

    expect($masked['street'])->toContain('Disamarkan untuk privasi')
        ->and($masked['street'])->not->toContain('No. 88')
        ->and($masked['city'])->toBe('Banjarmasin')
        ->and($masked['postal_code'])->toBe('70***');
});

test('public tracking route is rate limited to 30 requests per minute', function () {
    // Send 30 successful requests
    for ($i = 0; $i < 30; $i++) {
        $resp = $this->get(route('track.index'));
        $resp->assertOk();
    }

    // The 31st request should be throttled with HTTP 429
    $throttledResp = $this->get(route('track.index'));
    $throttledResp->assertStatus(429);
});

test('welcome landing page contains link to public cargo tracking', function () {
    $response = $this->get('/');

    $response->assertOk()
        ->assertSee(route('track.index'))
        ->assertSee('Lacak Kargo');
});
