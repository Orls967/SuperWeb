<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Modules\Banking\Application\Actions\SetPinAction;
use Modules\Banking\Application\Actions\TopUpAction;
use Modules\Banking\database\seeders\BankingSeeder;
use Modules\Logistics\Domain\Enums\LocationType;
use Modules\Logistics\Domain\Enums\ServiceLevel;
use Modules\Logistics\Domain\Enums\TransportMode;
use Modules\Logistics\Domain\Models\Location;
use Modules\Logistics\Domain\Models\RateBracket;
use Modules\Logistics\Domain\Models\RateCard;
use Modules\Logistics\Domain\Models\Shipment;
use Modules\Logistics\Domain\Models\ShipperAccount;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(BankingSeeder::class);

    $this->shipper = User::factory()->create([
        'role' => 'shipper',
        'name' => 'PT Sumber Kargo Banua',
        'email' => 'sumberkargo@shipper.test',
    ]);

    app(SetPinAction::class)->execute($this->shipper, '123456');
    app(TopUpAction::class)->execute($this->shipper, '10000000', 'IDR', 'topup_'.uniqid());

    $this->account = ShipperAccount::create([
        'shipper_id' => $this->shipper->id,
        'credit_limit_idr' => 20_000_000,
        'payment_terms_days' => 30,
        'is_active' => true,
    ]);

    $this->locA = Location::create([
        'code' => 'HUB-BDJ',
        'name' => 'Banjarmasin Central Hub',
        'type' => LocationType::HUB,
        'city' => 'Banjarmasin',
        'province' => 'Kalimantan Selatan',
        'country_code' => 'ID',
        'lat_e6' => -3316694,
        'lng_e6' => 114590111,
        'timezone' => 'Asia/Makassar',
        'min_connection_minutes' => 60,
    ]);

    $this->locB = Location::create([
        'code' => 'HUB-BJB',
        'name' => 'Banjarbaru Cargo Hub',
        'type' => LocationType::HUB,
        'city' => 'Banjarbaru',
        'province' => 'Kalimantan Selatan',
        'country_code' => 'ID',
        'lat_e6' => -3440000,
        'lng_e6' => 114840000,
        'timezone' => 'Asia/Makassar',
        'min_connection_minutes' => 60,
    ]);

    $this->rateCard = RateCard::create([
        'name' => 'Tarif BDJ - BJB Portal',
        'origin_location_id' => $this->locA->id,
        'destination_location_id' => $this->locB->id,
        'service_level' => ServiceLevel::Regular,
        'mode' => TransportMode::ROAD,
        'min_charge_idr' => 10_000,
        'valid_from' => '2026-01-01',
        'valid_to' => null,
        'is_active' => true,
    ]);

    RateBracket::create([
        'rate_card_id' => $this->rateCard->id,
        'min_weight_kg' => 0,
        'max_weight_kg' => 100,
        'rate_per_kg_idr' => 12_000,
        'is_flat' => false,
    ]);
});

test('shipper can view their shipments list and create shipment form', function () {
    $response = $this->actingAs($this->shipper)->get(route('logistics.shipments.index'));
    $response->assertOk()
        ->assertSee('Portal Pengiriman (Shipper Portal)');

    $responseCreate = $this->actingAs($this->shipper)->get(route('logistics.shipments.create'));
    $responseCreate->assertOk()
        ->assertSee('Kirim Kargo Baru')
        ->assertSee('Banjarmasin Central Hub');
});

test('shipper can create and book a shipment via form submission', function () {
    $payload = [
        'origin_location_id' => $this->locA->id,
        'destination_location_id' => $this->locB->id,
        'service_level' => ServiceLevel::Regular->value,
        'consignee_name' => 'Haji Mansyur',
        'consignee_phone' => '081234567890',
        'consignee_street' => 'Jl. Ahmad Yani KM 33',
        'consignee_city' => 'Banjarbaru',
        'consignee_postal_code' => '70714',
        'payment_terms' => 'prepaid',
        'pin' => '123456',
        'packages' => [
            [
                'description' => 'Alat Pertukangan Kayu',
                'weight_g' => 2500,
                'length_mm' => 300,
                'width_mm' => 200,
                'height_mm' => 100,
            ],
        ],
    ];

    $response = $this->actingAs($this->shipper)
        ->post(route('logistics.shipments.store'), $payload);

    $response->assertRedirect();

    expect(Shipment::count())->toBe(1);
    $shipment = Shipment::first();
    expect($shipment->shipper_id)->toBe($this->shipper->id)
        ->and($shipment->consignee_name)->toBe('Haji Mansyur')
        ->and($shipment->packages()->count())->toBe(1);
});

test('shipper can view shipment detail and print QR label', function () {
    $payload = [
        'origin_location_id' => $this->locA->id,
        'destination_location_id' => $this->locB->id,
        'service_level' => ServiceLevel::Regular->value,
        'consignee_name' => 'Haji Mansyur',
        'consignee_phone' => '081234567890',
        'consignee_street' => 'Jl. Ahmad Yani KM 33',
        'consignee_city' => 'Banjarbaru',
        'payment_terms' => 'postpaid',
        'packages' => [
            ['description' => 'Paket A', 'weight_g' => 1000, 'length_mm' => 100, 'width_mm' => 100, 'height_mm' => 100],
        ],
    ];
    $this->actingAs($this->shipper)->post(route('logistics.shipments.store'), $payload);
    $shipment = Shipment::firstOrFail();

    // Show page
    $responseShow = $this->actingAs($this->shipper)->get(route('logistics.shipments.show', $shipment->id));
    $responseShow->assertOk()
        ->assertSee($shipment->tracking_number)
        ->assertSee('Haji Mansyur');

    // Label print page
    $responseLabel = $this->actingAs($this->shipper)->get(route('logistics.shipments.label', $shipment->id));
    $responseLabel->assertOk()
        ->assertSee($shipment->tracking_number)
        ->assertSee('<svg', false) // Contains QR SVG
        ->assertSee('SARI RANAH EXPRESS');
});

test('shipper cannot view another shippers shipment (IDOR protection)', function () {
    $otherShipper = User::factory()->create([
        'role' => 'shipper',
        'email' => 'other_user@shipper.test',
    ]);

    $payload = [
        'origin_location_id' => $this->locA->id,
        'destination_location_id' => $this->locB->id,
        'service_level' => ServiceLevel::Regular->value,
        'consignee_name' => 'Rahasia Shipper A',
        'consignee_phone' => '081234567890',
        'consignee_street' => 'Jl. Rahasia',
        'consignee_city' => 'Banjarbaru',
        'payment_terms' => 'postpaid',
        'packages' => [
            ['description' => 'Dokumen Rahasia', 'weight_g' => 500, 'length_mm' => 50, 'width_mm' => 50, 'height_mm' => 50],
        ],
    ];
    $this->actingAs($this->shipper)->post(route('logistics.shipments.store'), $payload);
    $shipment = Shipment::firstOrFail();

    // Other shipper attempt to access show and label
    $this->actingAs($otherShipper)->get(route('logistics.shipments.show', $shipment->id))
        ->assertForbidden();

    $this->actingAs($otherShipper)->get(route('logistics.shipments.label', $shipment->id))
        ->assertForbidden();
});

test('shipper can download template and upload bulk CSV shipments', function () {
    // 1. Download template
    $responseTemplate = $this->actingAs($this->shipper)->get(route('logistics.shipments.template'));
    $responseTemplate->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8')
        ->assertSee('origin_code,destination_code,service_level');

    // 2. Prepare sample CSV with 2 valid rows and 1 invalid row
    $csvContent = "origin_code,destination_code,service_level,consignee_name,consignee_phone,consignee_address,weight_g,length_mm,width_mm,height_mm,description\n";
    $csvContent .= "HUB-BDJ,HUB-BJB,regular,Penerima Valid 1,0811111111,Jl. Melati No. 1,2000,100,100,100,Kargo Sukses 1\n";
    $csvContent .= "HUB-BDJ,HUB-BJB,regular,Penerima Valid 2,0822222222,Jl. Mawar No. 2,3000,150,150,150,Kargo Sukses 2\n";
    $csvContent .= "HUB-BDJ,INVALID-HUB,regular,Penerima Gagal,0833333333,Jl. Rusak,1000,100,100,100,Kargo Gagal\n";

    $tempCsv = UploadedFile::fake()->createWithContent('kargo_bulk.csv', $csvContent);

    // 3. Upload CSV
    $responseUpload = $this->actingAs($this->shipper)->post(route('logistics.shipments.bulk.process'), [
        'csv_file' => $tempCsv,
    ]);

    $responseUpload->assertRedirect(route('logistics.shipments.bulk'));

    // 2 shipments successfully created
    expect(Shipment::where('shipper_id', $this->shipper->id)->count())->toBe(2);

    $batchId = session('batch_id');
    expect($batchId)->not->toBeNull();

    // 4. Download error report
    $responseError = $this->actingAs($this->shipper)->get(route('logistics.shipments.errors', $batchId));
    $responseError->assertOk()
        ->assertSee('INVALID-HUB');
});
