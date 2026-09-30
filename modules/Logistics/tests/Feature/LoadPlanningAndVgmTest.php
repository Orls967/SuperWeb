<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Logistics\Application\Actions\ConsolidateLclAction;
use Modules\Logistics\Application\Actions\RecordSolasVgmAction;
use Modules\Logistics\Domain\Enums\FleetStatus;
use Modules\Logistics\Domain\Enums\LocationType;
use Modules\Logistics\Domain\Enums\PaymentTerms;
use Modules\Logistics\Domain\Enums\ScheduleStatus;
use Modules\Logistics\Domain\Enums\ServiceLevel;
use Modules\Logistics\Domain\Enums\ShipmentStatus;
use Modules\Logistics\Domain\Enums\TransportMode;
use Modules\Logistics\Domain\Exceptions\ActiveLoadConflictException;
use Modules\Logistics\Domain\Exceptions\InvalidLoadConsolidationException;
use Modules\Logistics\Domain\Exceptions\MissingSolasVgmException;
use Modules\Logistics\Domain\Models\Container;
use Modules\Logistics\Domain\Models\Load;
use Modules\Logistics\Domain\Models\Location;
use Modules\Logistics\Domain\Models\Package;
use Modules\Logistics\Domain\Models\Schedule;
use Modules\Logistics\Domain\Models\Shipment;
use Modules\Logistics\Domain\Models\Vessel;
use Modules\Logistics\Domain\ValueObjects\Iso6346Validator;
use Modules\Logistics\Domain\ValueObjects\TrackingNumber;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->portBdj = Location::create([
        'code' => 'PORT-BDJ',
        'name' => 'Pelabuhan Trisakti',
        'type' => LocationType::SEAPORT,
        'city' => 'Banjarmasin',
        'province' => 'Kalimantan Selatan',
        'country_code' => 'ID',
        'lat_e6' => -3330000,
        'lng_e6' => 114570000,
        'timezone' => 'Asia/Makassar',
        'min_connection_minutes' => 180,
    ]);

    $this->portSub = Location::create([
        'code' => 'PORT-SUB',
        'name' => 'Pelabuhan Tanjung Perak',
        'type' => LocationType::SEAPORT,
        'city' => 'Surabaya',
        'province' => 'Jawa Timur',
        'country_code' => 'ID',
        'lat_e6' => -7200000,
        'lng_e6' => 112730000,
        'timezone' => 'Asia/Jakarta',
        'min_connection_minutes' => 180,
    ]);

    $this->cfs = Location::create([
        'code' => 'CFS-BDJ',
        'name' => 'CFS Trisakti Banjarmasin',
        'type' => LocationType::CFS,
        'city' => 'Banjarmasin',
        'province' => 'Kalimantan Selatan',
        'country_code' => 'ID',
        'lat_e6' => -3331000,
        'lng_e6' => 114571000,
        'timezone' => 'Asia/Makassar',
        'min_connection_minutes' => 120,
    ]);

    $this->container1 = Container::create([
        'container_number' => 'CSQU3054383', // Valid ISO 6346
        'size_type' => '22G1',
        'tare_kg' => 2200,
        'max_gross_kg' => 30480,
        'status' => FleetStatus::AVAILABLE,
        'current_location_id' => $this->portBdj->id,
    ]);

    $this->container2 = Container::create([
        'container_number' => Iso6346Validator::generate('SRX', 'U', 200001),
        'size_type' => '22G1',
        'tare_kg' => 2200,
        'max_gross_kg' => 30480,
        'status' => FleetStatus::AVAILABLE,
        'current_location_id' => $this->portBdj->id,
    ]);
});

test('container cannot be assigned to two active loads simultaneously', function () {
    // 1. First active load
    Load::create([
        'load_number' => 'LOD-FCL-001',
        'load_type' => 'container',
        'loadable_type' => Container::class,
        'loadable_id' => $this->container1->id,
        'service_type' => 'fcl',
        'origin_location_id' => $this->portBdj->id,
        'destination_location_id' => $this->portSub->id,
        'status' => 'planning',
        'max_weight_kg' => '28000.000',
        'max_volume_dm3' => 33000,
    ]);

    // 2. Attempting to assign same container to second active load throws exception
    expect(fn () => Load::assertUnitNotActive(Container::class, $this->container1->id))
        ->toThrow(ActiveLoadConflictException::class);
});

test('fcl container strictly allows only one shipment and rejects second shipment', function () {
    $load = Load::create([
        'load_number' => 'LOD-FCL-TEST',
        'load_type' => 'container',
        'loadable_type' => Container::class,
        'loadable_id' => $this->container1->id,
        'service_type' => 'fcl',
        'origin_location_id' => $this->portBdj->id,
        'destination_location_id' => $this->portSub->id,
        'status' => 'planning',
        'max_weight_kg' => '28000.000',
        'max_volume_dm3' => 33000,
    ]);

    $user = User::factory()->create();
    $shipmentA = Shipment::create([
        'tracking_number' => TrackingNumber::generate(),
        'shipper_id' => $user->id,
        'consignee_name' => 'PT Semen Padang',
        'consignee_phone' => '081234567890',
        'consignee_address' => ['street' => 'Jl. Pelabuhan', 'city' => 'Surabaya'],
        'origin_location_id' => $this->portBdj->id,
        'destination_location_id' => $this->portSub->id,
        'service_level' => ServiceLevel::FCL,
        'mode' => TransportMode::SEA,
        'payment_terms' => PaymentTerms::Postpaid,
        'status' => ShipmentStatus::Booked,
        'total_chargeable_weight_g' => 20000000,
        'total_amount_idr' => 15000000,
    ]);

    $shipmentB = Shipment::create([
        'tracking_number' => TrackingNumber::generate(),
        'shipper_id' => $user->id,
        'consignee_name' => 'PT Berkah Logistik',
        'consignee_phone' => '081234567891',
        'consignee_address' => ['street' => 'Jl. Perak Barat', 'city' => 'Surabaya'],
        'origin_location_id' => $this->portBdj->id,
        'destination_location_id' => $this->portSub->id,
        'service_level' => ServiceLevel::FCL,
        'mode' => TransportMode::SEA,
        'payment_terms' => PaymentTerms::Postpaid,
        'status' => ShipmentStatus::Booked,
        'total_chargeable_weight_g' => 5000000,
        'total_amount_idr' => 5000000,
    ]);

    // 1. Add item for Shipment A succeeds
    $load->addItem($shipmentA, null, 15000, 20000);
    expect($load->items)->toHaveCount(1);

    // 2. Add item for Shipment B fails because FCL allows only 1 shipment
    expect(fn () => $load->addItem($shipmentB, null, 2000, 3000))
        ->toThrow(InvalidLoadConsolidationException::class);
});

test('lcl consolidation at cfs packs packages using first fit decreasing algorithm', function () {
    $user = User::factory()->create();

    $shipment1 = Shipment::create([
        'tracking_number' => TrackingNumber::generate(),
        'shipper_id' => $user->id,
        'consignee_name' => 'Toko Banua',
        'consignee_phone' => '0811111111',
        'consignee_address' => ['street' => 'Jl. A', 'city' => 'Surabaya'],
        'origin_location_id' => $this->cfs->id,
        'destination_location_id' => $this->portSub->id,
        'service_level' => ServiceLevel::LCL,
        'mode' => TransportMode::SEA,
        'payment_terms' => PaymentTerms::Prepaid,
        'status' => ShipmentStatus::Booked,
        'total_chargeable_weight_g' => 3000000,
        'total_amount_idr' => 2000000,
    ]);

    Package::create([
        'shipment_id' => $shipment1->id,
        'weight_g' => 1500000,
        'length_mm' => 1500,
        'width_mm' => 1000,
        'height_mm' => 1000, // 1.5 CBM (1500 dm3)
        'description' => 'Paket Mesin Traktor',
    ]);

    $shipment2 = Shipment::create([
        'tracking_number' => TrackingNumber::generate(),
        'shipper_id' => $user->id,
        'consignee_name' => 'Toko Sahabat',
        'consignee_phone' => '0822222222',
        'consignee_address' => ['street' => 'Jl. B', 'city' => 'Surabaya'],
        'origin_location_id' => $this->cfs->id,
        'destination_location_id' => $this->portSub->id,
        'service_level' => ServiceLevel::LCL,
        'mode' => TransportMode::SEA,
        'payment_terms' => PaymentTerms::Prepaid,
        'status' => ShipmentStatus::Booked,
        'total_chargeable_weight_g' => 2000000,
        'total_amount_idr' => 1500000,
    ]);

    Package::create([
        'shipment_id' => $shipment2->id,
        'weight_g' => 2000000,
        'length_mm' => 2000,
        'width_mm' => 1000,
        'height_mm' => 1000, // 2.0 CBM (2000 dm3)
        'description' => 'Paket Suku Cadang Kapal',
    ]);

    $action = app(ConsolidateLclAction::class);
    $loads = $action->execute(
        cfsLocation: $this->cfs,
        destinationLocation: $this->portSub,
        shipments: [$shipment1, $shipment2],
        availableContainers: [$this->container1, $this->container2]
    );

    expect($loads)->toHaveCount(1);
    $load = $loads[0];
    expect($load->items)->toHaveCount(2);

    // Verify First-Fit-Decreasing: item with larger volume (2000 dm3) was loaded first (sequence 1)
    expect($load->items[0]->volume_dm3)->toBe(2000)
        ->and($load->items[1]->volume_dm3)->toBe(1500)
        ->and($load->current_volume_dm3)->toBe(3500);
});

test('dangerous goods segregation rejects co loading incompatible classes', function () {
    $load = Load::create([
        'load_number' => 'LOD-DG-TEST',
        'load_type' => 'container',
        'loadable_type' => Container::class,
        'loadable_id' => $this->container1->id,
        'service_type' => 'lcl',
        'origin_location_id' => $this->portBdj->id,
        'destination_location_id' => $this->portSub->id,
        'status' => 'planning',
        'max_weight_kg' => '28000.000',
        'max_volume_dm3' => 33000,
    ]);

    $user = User::factory()->create();
    $shipmentA = Shipment::create([
        'tracking_number' => TrackingNumber::generate(),
        'shipper_id' => $user->id,
        'consignee_name' => 'PT Tambang Kalsel',
        'consignee_phone' => '081234567890',
        'consignee_address' => ['street' => 'Jl. Tambang', 'city' => 'Surabaya'],
        'origin_location_id' => $this->portBdj->id,
        'destination_location_id' => $this->portSub->id,
        'service_level' => ServiceLevel::LCL,
        'mode' => TransportMode::SEA,
        'payment_terms' => PaymentTerms::Prepaid,
        'status' => ShipmentStatus::Booked,
        'total_chargeable_weight_g' => 500000,
        'total_amount_idr' => 1000000,
    ]);

    $shipmentB = Shipment::create([
        'tracking_number' => TrackingNumber::generate(),
        'shipper_id' => $user->id,
        'consignee_name' => 'PT Kimia Industri',
        'consignee_phone' => '081234567891',
        'consignee_address' => ['street' => 'Jl. Industri', 'city' => 'Surabaya'],
        'origin_location_id' => $this->portBdj->id,
        'destination_location_id' => $this->portSub->id,
        'service_level' => ServiceLevel::LCL,
        'mode' => TransportMode::SEA,
        'payment_terms' => PaymentTerms::Prepaid,
        'status' => ShipmentStatus::Booked,
        'total_chargeable_weight_g' => 500000,
        'total_amount_idr' => 1000000,
    ]);

    // 1. Load DG Class 1 (Explosives)
    $load->addItem($shipmentA, null, 200, 500, dgClass: '1');

    // 2. Load DG Class 3 (Flammable Liquids) -> Incompatible under IMDG Code!
    expect(fn () => $load->addItem($shipmentB, null, 100, 300, dgClass: '3'))
        ->toThrow(InvalidLoadConsolidationException::class);
});

test('solas vgm is strictly mandatory before container can be loaded onto maritime vessel schedule', function () {
    $vessel = Vessel::create([
        'name' => 'KM Kumala Bahari',
        'imo_number' => '9074729',
        'flag' => 'ID',
        'dwt_tonnes' => 8000,
        'teu_capacity' => 450,
        'status' => FleetStatus::AVAILABLE,
    ]);

    $seaSchedule = Schedule::create([
        'schedule_number' => 'VOY-VGM-001',
        'mode' => TransportMode::SEA,
        'asset_type' => Vessel::class,
        'asset_id' => $vessel->id,
        'origin_location_id' => $this->portBdj->id,
        'destination_location_id' => $this->portSub->id,
        'etd' => now()->addDays(2),
        'eta' => now()->addDays(3),
        'cutoff_at' => now()->addDay(),
        'status' => ScheduleStatus::Scheduled,
        'cap_weight_kg' => '5000000.000',
        'cap_volume_dm3' => 8000000,
        'cap_teu' => 450,
    ]);

    $load = Load::create([
        'load_number' => 'LOD-MAR-001',
        'load_type' => 'container',
        'loadable_type' => Container::class,
        'loadable_id' => $this->container1->id,
        'service_type' => 'fcl',
        'origin_location_id' => $this->portBdj->id,
        'destination_location_id' => $this->portSub->id,
        'status' => 'sealed',
        'max_weight_kg' => '28000.000',
        'max_volume_dm3' => 33000,
        'current_weight_kg' => '15000.000',
        'current_volume_dm3' => 20000,
    ]);

    // 1. Attempting to load onto vessel without VGM certification is rejected
    expect(fn () => $load->loadOntoSchedule($seaSchedule))
        ->toThrow(MissingSolasVgmException::class);

    // 2. Certify SOLAS VGM
    $vgmAction = app(RecordSolasVgmAction::class);
    $vgmAction->execute(
        load: $load,
        vgmKg: '17200.000', // 15,000 kg cargo + 2,200 kg tare
        method: 'method_1',
        certifiedBy: 'Capt. H. Sulaiman (Marine Surveyor)'
    );

    expect($load->hasVgm())->toBeTrue()
        ->and($load->vgm_kg)->toBe('17200.000')
        ->and($load->vgm_method)->toBe('method_1');

    // 3. Loading onto vessel schedule now succeeds
    $load->loadOntoSchedule($seaSchedule);
    expect($load->status)->toBe('loaded')
        ->and($load->schedule_id)->toBe($seaSchedule->id);
});
