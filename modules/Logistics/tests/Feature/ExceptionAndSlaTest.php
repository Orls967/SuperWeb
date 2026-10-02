<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Logistics\Application\Actions\ProcessHubInboundScanAction;
use Modules\Logistics\Application\Actions\RaiseShipmentExceptionAction;
use Modules\Logistics\Application\Actions\ReportFailedDeliveryAction;
use Modules\Logistics\Application\Actions\ResolveShipmentExceptionAction;
use Modules\Logistics\Application\Actions\StartDeliveryAction;
use Modules\Logistics\Domain\Enums\DeliveryFailureReason;
use Modules\Logistics\Domain\Enums\ExceptionSeverity;
use Modules\Logistics\Domain\Enums\ExceptionType;
use Modules\Logistics\Domain\Enums\LocationType;
use Modules\Logistics\Domain\Enums\PaymentTerms;
use Modules\Logistics\Domain\Enums\ServiceLevel;
use Modules\Logistics\Domain\Enums\ShipmentStatus;
use Modules\Logistics\Domain\Enums\TransportMode;
use Modules\Logistics\Domain\Exceptions\InvalidDeliveryOperationException;
use Modules\Logistics\Domain\Models\Driver;
use Modules\Logistics\Domain\Models\Location;
use Modules\Logistics\Domain\Models\Shipment;
use Modules\Logistics\Domain\Models\ShipmentException;
use Modules\Logistics\Domain\Models\TrackingEvent;
use Modules\Logistics\Domain\Services\DeliverySlaPolicy;
use Modules\Logistics\Domain\ValueObjects\TrackingNumber;

uses(RefreshDatabase::class);

beforeEach(function () {
    $mk = fn (string $code, string $city) => Location::create([
        'code' => $code, 'name' => "Hub {$city}", 'type' => LocationType::HUB, 'city' => $city, 'province' => 'Kalimantan Selatan',
        'country_code' => 'ID', 'lat_e6' => -3300000, 'lng_e6' => 114500000, 'timezone' => 'Asia/Makassar', 'min_connection_minutes' => 60,
    ]);
    $this->hubA = $mk('HUB-BDJ', 'Banjarmasin');
    $this->hubB = $mk('HUB-BJB', 'Banjarbaru');
    $this->hubC = $mk('HUB-PKY', 'Palangkaraya');
    $this->shipper = User::factory()->create(['role' => 'shipper']);
    $this->dispatcher = User::factory()->create(['role' => 'dispatcher']);
});

function slaShipment(object $t, ServiceLevel $level, ShipmentStatus $status, int $bookedHoursAgo): Shipment
{
    return Shipment::create([
        'tracking_number' => TrackingNumber::generate(), 'shipper_id' => $t->shipper->id,
        'consignee_name' => 'Siti', 'consignee_phone' => '081200000000', 'consignee_address' => ['street' => 'Jl. A 1', 'city' => 'Banjarbaru'],
        'origin_location_id' => $t->hubA->id, 'destination_location_id' => $t->hubB->id,
        'service_level' => $level, 'mode' => TransportMode::ROAD, 'payment_terms' => PaymentTerms::Prepaid,
        'status' => $status, 'total_chargeable_weight_g' => 1000, 'total_amount_idr' => 50000, 'booked_at' => now()->subHours($bookedHoursAgo),
    ]);
}

test('sla due time depends on service level', function () {
    $policy = app(DeliverySlaPolicy::class);
    $express = slaShipment($this, ServiceLevel::Express, ShipmentStatus::InTransit, 0);
    $regular = slaShipment($this, ServiceLevel::Regular, ShipmentStatus::InTransit, 0);

    expect($policy->hoursFor(ServiceLevel::Express))->toBe(24)
        ->and((int) $express->booked_at->diffInHours($policy->dueAt($express)))->toBe(24)
        ->and((int) $regular->booked_at->diffInHours($policy->dueAt($regular)))->toBe(72);
});

test('breached and at risk queries select only active shipments past or close to their sla', function () {
    $policy = app(DeliverySlaPolicy::class);
    $late = slaShipment($this, ServiceLevel::Express, ShipmentStatus::InTransit, 30);        // 24h SLA, 30h ago -> lewat
    $risk = slaShipment($this, ServiceLevel::Express, ShipmentStatus::AtHub, 20);            // jatuh tempo 4 jam lagi
    $safe = slaShipment($this, ServiceLevel::Express, ShipmentStatus::InTransit, 2);
    $doneLate = slaShipment($this, ServiceLevel::Express, ShipmentStatus::Delivered, 40);    // selesai: tidak dihitung
    $cancelled = slaShipment($this, ServiceLevel::Express, ShipmentStatus::Cancelled, 40);
    $slowButOk = slaShipment($this, ServiceLevel::Regular, ShipmentStatus::InTransit, 30);   // 72h SLA

    expect($policy->breachedQuery()->pluck('id')->all())->toBe([$late->id])
        ->and($policy->atRiskQuery()->pluck('id')->all())->toBe([$risk->id])
        ->and($policy->isBreached($late))->toBeTrue()
        ->and($policy->isBreached($safe))->toBeFalse()
        ->and($policy->isBreached($doneLate))->toBeFalse();
});

test('lgx detect late is idempotent', function () {
    $late = slaShipment($this, ServiceLevel::Express, ShipmentStatus::InTransit, 30);
    slaShipment($this, ServiceLevel::Express, ShipmentStatus::InTransit, 2);

    $this->artisan('lgx:detect-late')->expectsOutputToContain('baru: 1')->assertSuccessful();
    $this->artisan('lgx:detect-late')->expectsOutputToContain('baru: 0; sudah tercatat: 1')->assertSuccessful();

    $exceptions = ShipmentException::where('type', ExceptionType::Late->value)->get();
    expect($exceptions)->toHaveCount(1)
        ->and($exceptions[0]->shipment_id)->toBe($late->id)
        ->and($exceptions[0]->severity)->toBe(ExceptionSeverity::High)
        ->and($exceptions[0]->status)->toBe('open')
        ->and($exceptions[0]->dedupe_key)->toBe('late:'.$late->id);
});

test('detect late dry run stores nothing and delivered shipments close their late exception', function () {
    $late = slaShipment($this, ServiceLevel::Express, ShipmentStatus::InTransit, 30);

    $this->artisan('lgx:detect-late', ['--dry-run' => true])->assertSuccessful();
    expect(ShipmentException::count())->toBe(0);

    $this->artisan('lgx:detect-late')->assertSuccessful();
    $late->update(['status' => ShipmentStatus::Delivered]);
    $this->artisan('lgx:detect-late')->expectsOutputToContain('ditutup otomatis: 1')->assertSuccessful();

    expect(ShipmentException::first()->status)->toBe('resolved');
});

test('raising with the same dedupe key returns the existing exception', function () {
    $shipment = slaShipment($this, ServiceLevel::Regular, ShipmentStatus::InTransit, 1);
    $action = app(RaiseShipmentExceptionAction::class);

    $a = $action->execute($shipment, ExceptionType::Other, 'Pertama', dedupeKey: 'k1');
    $b = $action->execute($shipment, ExceptionType::Other, 'Kedua', dedupeKey: 'k1');

    expect($b->id)->toBe($a->id)->and(ShipmentException::count())->toBe(1);
});

test('manual blocking exception moves the shipment to exception and records custody event', function () {
    $shipment = slaShipment($this, ServiceLevel::Regular, ShipmentStatus::InTransit, 1);

    $ex = app(RaiseShipmentExceptionAction::class)->execute($shipment, ExceptionType::Damaged, 'Kardus penyok berat', $this->dispatcher, markShipment: true);

    expect($ex->severity)->toBe(ExceptionSeverity::Critical)
        ->and($shipment->fresh()->status)->toBe(ShipmentStatus::Exception)
        ->and(TrackingEvent::where('shipment_id', $shipment->id)->value('event_type'))->toBe('EXCEPTION_RAISED');
});

test('non blocking exception keeps the shipment status', function () {
    $shipment = slaShipment($this, ServiceLevel::Regular, ShipmentStatus::InTransit, 1);

    app(RaiseShipmentExceptionAction::class)->execute($shipment, ExceptionType::WeatherDelay, 'Hujan lebat', $this->dispatcher, markShipment: true);

    expect($shipment->fresh()->status)->toBe(ShipmentStatus::InTransit);
});

test('resolving the last open exception requires a valid resume status', function () {
    $shipment = slaShipment($this, ServiceLevel::Regular, ShipmentStatus::InTransit, 1);
    $ex = app(RaiseShipmentExceptionAction::class)->execute($shipment, ExceptionType::AddressInvalid, 'Alamat tidak lengkap', $this->dispatcher, markShipment: true);
    $resolve = app(ResolveShipmentExceptionAction::class);

    expect(fn () => $resolve->execute($ex, $this->dispatcher, 'Alamat sudah dikonfirmasi'))->toThrow(InvalidDeliveryOperationException::class, 'status lanjutan');
    expect(fn () => $resolve->execute($ex, $this->dispatcher, 'x', ShipmentStatus::Delivered))->toThrow(InvalidDeliveryOperationException::class);
    expect($ex->fresh()->status)->toBe('open');

    $resolve->execute($ex, $this->dispatcher, 'Alamat sudah dikonfirmasi', ShipmentStatus::InTransit);

    expect($ex->fresh()->status)->toBe('resolved')
        ->and($ex->fresh()->resolved_by)->toBe($this->dispatcher->id)
        ->and($shipment->fresh()->status)->toBe(ShipmentStatus::InTransit)
        ->and(TrackingEvent::where('shipment_id', $shipment->id)->orderByDesc('sequence')->value('event_type'))->toBe('EXCEPTION_RESOLVED');

    expect(fn () => $resolve->execute($ex, $this->dispatcher, 'lagi', ShipmentStatus::InTransit))->toThrow(InvalidDeliveryOperationException::class, 'sudah diselesaikan');
});

test('shipment stays in exception while another exception is still open', function () {
    $shipment = slaShipment($this, ServiceLevel::Regular, ShipmentStatus::InTransit, 1);
    $raise = app(RaiseShipmentExceptionAction::class);
    $first = $raise->execute($shipment, ExceptionType::Damaged, 'Rusak', $this->dispatcher, markShipment: true);
    $second = $raise->execute($shipment, ExceptionType::VehicleBreakdown, 'Ban pecah', $this->dispatcher, markShipment: true);

    app(ResolveShipmentExceptionAction::class)->execute($first, $this->dispatcher, 'Rusak ringan, aman');

    expect($shipment->fresh()->status)->toBe(ShipmentStatus::Exception);

    app(ResolveShipmentExceptionAction::class)->execute($second, $this->dispatcher, 'Ban diganti', ShipmentStatus::InTransit);
    expect($shipment->fresh()->status)->toBe(ShipmentStatus::InTransit);
});

test('hub missort automatically raises a structured missort exception once', function () {
    $operator = User::factory()->create(['role' => 'hub_operator']);
    $shipment = slaShipment($this, ServiceLevel::Regular, ShipmentStatus::InTransit, 1);

    $result = app(ProcessHubInboundScanAction::class)->execute($shipment, $this->hubC, $operator);

    $ex = ShipmentException::where('shipment_id', $shipment->id)->get();
    expect($result['is_missort'])->toBeTrue()
        ->and($ex)->toHaveCount(1)
        ->and($ex[0]->type)->toBe(ExceptionType::Missort)
        ->and($ex[0]->location_id)->toBe($this->hubC->id)
        ->and($shipment->fresh()->status)->toBe(ShipmentStatus::Exception);
});

test('failed deliveries raise a delivery failed exception per attempt', function () {
    $driver = Driver::create([
        'user_id' => User::factory()->create(['role' => 'driver'])->id, 'driver_number' => 'DRV-EX-001', 'license_class' => 'SIM B1 Umum',
        'license_expiry' => now()->addYear(), 'home_hub_id' => $this->hubA->id, 'status' => 'available',
    ]);
    $shipment = slaShipment($this, ServiceLevel::Regular, ShipmentStatus::AtHub, 1);
    $shipment->update(['driver_id' => $driver->id]);
    app(StartDeliveryAction::class)->execute($driver, $shipment);
    $report = app(ReportFailedDeliveryAction::class);

    $report->execute($driver, $shipment, DeliveryFailureReason::ConsigneeUnavailable);
    $report->execute($driver, $shipment, DeliveryFailureReason::AddressNotFound);
    $report->execute($driver, $shipment, DeliveryFailureReason::Refused);

    $exceptions = ShipmentException::where('shipment_id', $shipment->id)->orderBy('id')->get();
    expect($exceptions)->toHaveCount(3)
        ->and($exceptions->pluck('type')->unique()->all())->toBe([ExceptionType::DeliveryFailed])
        ->and($exceptions[0]->severity)->toBe(ExceptionSeverity::Medium)
        ->and($exceptions[2]->severity)->toBe(ExceptionSeverity::High);
});

test('exception and sla page is limited to operational staff and shows data', function () {
    $late = slaShipment($this, ServiceLevel::Express, ShipmentStatus::InTransit, 30);
    $this->artisan('lgx:detect-late');

    foreach (['dispatcher', 'logistics_admin', 'admin', 'hub_operator'] as $role) {
        $this->actingAs(User::factory()->create(['role' => $role]))->get(route('logistics.exceptions.index'))
            ->assertOk()->assertSee($late->tracking_number)->assertSee('Terlambat (SLA Terlampaui)');
    }
    foreach (['shipper', 'driver', 'customer'] as $role) {
        $this->actingAs(User::factory()->create(['role' => $role]))->get(route('logistics.exceptions.index'))->assertForbidden();
    }
});

test('staff can report and dispatcher can resolve through http but hub operator cannot resolve', function () {
    $shipment = slaShipment($this, ServiceLevel::Regular, ShipmentStatus::InTransit, 1);
    $operator = User::factory()->create(['role' => 'hub_operator']);

    $this->actingAs($operator)->post(route('logistics.exceptions.store'), [
        'tracking_number' => $shipment->tracking_number, 'type' => 'damaged', 'description' => 'Kemasan basah',
    ])->assertSessionHas('success');
    expect($shipment->fresh()->status)->toBe(ShipmentStatus::Exception);

    $ex = ShipmentException::firstOrFail();

    $this->actingAs($operator)->post(route('logistics.exceptions.resolve', $ex->id), ['notes' => 'ok', 'resume_to' => 'in_transit'])->assertForbidden();

    $this->actingAs($this->dispatcher)->post(route('logistics.exceptions.resolve', $ex->id), ['notes' => 'Dikemas ulang'])
        ->assertSessionHas('error');
    $this->actingAs($this->dispatcher)->post(route('logistics.exceptions.resolve', $ex->id), ['notes' => 'Dikemas ulang', 'resume_to' => 'in_transit'])
        ->assertSessionHas('success');
    expect($ex->fresh()->status)->toBe('resolved')->and($shipment->fresh()->status)->toBe(ShipmentStatus::InTransit);
});

test('manual report validates type, resi and shipment state', function () {
    $delivered = slaShipment($this, ServiceLevel::Regular, ShipmentStatus::Delivered, 1);

    $this->actingAs($this->dispatcher)->post(route('logistics.exceptions.store'), ['tracking_number' => 'NOPE', 'type' => 'damaged', 'description' => 'x'])
        ->assertSessionHas('error');
    $this->actingAs($this->dispatcher)->post(route('logistics.exceptions.store'), ['tracking_number' => $delivered->tracking_number, 'type' => 'damaged', 'description' => 'x'])
        ->assertSessionHas('error');
    $this->actingAs($this->dispatcher)->post(route('logistics.exceptions.store'), ['tracking_number' => $delivered->tracking_number, 'type' => 'late', 'description' => 'x'])
        ->assertSessionHasErrors('type');
    expect(ShipmentException::count())->toBe(0);
});
