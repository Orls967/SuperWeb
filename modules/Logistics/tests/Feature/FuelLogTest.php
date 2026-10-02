<?php

declare(strict_types=1);

use App\Models\Brand;
use App\Models\Car;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Core\Contracts\AcquiresVehicle;
use Modules\Logistics\Application\Actions\RecordFuelLogAction;
use Modules\Logistics\Application\Services\LogisticsLedger;
use Modules\Logistics\Domain\Enums\FleetStatus;
use Modules\Logistics\Domain\Enums\ScheduleStatus;
use Modules\Logistics\Domain\Enums\TransportMode;
use Modules\Logistics\Domain\Enums\TruckType;
use Modules\Logistics\Domain\Exceptions\FuelLogException;
use Modules\Logistics\Domain\Models\FuelLog;
use Modules\Logistics\Domain\Models\Schedule;
use Modules\Logistics\Domain\Models\Truck;
use Modules\Logistics\Domain\Services\FuelConsumptionAnalyzer;
use Modules\Logistics\tests\Support\MoneyFlowWorld;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->world = new MoneyFlowWorld;
    $brand = Brand::firstOrCreate(['slug' => 'isuzu'], ['name' => 'Isuzu', 'country' => 'Japan', 'category' => 'other', 'is_active' => true]);
    $car = Car::firstOrCreate(
        ['slug' => 'isuzu-giga-fvr'],
        ['brand_id' => $brand->id, 'model' => 'Giga FVR', 'year_start' => 2022, 'body_type' => 'Truck', 'fuel_type' => 'diesel', 'price_idr' => 750000000, 'is_active' => true]
    );
    $vehicle = app(AcquiresVehicle::class)->handle(user: $this->world->dispatcher, car: $car, plateNumber: 'DA 7700 TA', color: 'White', vin: 'MHFVR34P0NK770001');
    $this->truck = Truck::create([
        'vehicle_id' => $vehicle->id, 'plate_number' => 'DA 7700 TA', 'type' => TruckType::CDD, 'payload_kg' => 5000, 'volume_dm3' => 14000,
        'required_license' => 'SIM B1 Umum', 'service_interval_m' => 10_000_000, 'odometer_m' => 0, 'status' => FleetStatus::AVAILABLE,
        'current_location_id' => $this->world->hubA->id,
    ]);
    $this->fill = fn (int $km, float $liters, int $price = 15_000, $user = null, $driver = null) => app(RecordFuelLogAction::class)->execute(
        $user ?? $this->world->dispatcher, $this->truck, (int) round($liters * 1000), $price, $km * 1000, null, $driver
    );
});

function fuelBal(string $code): int
{
    return (int) (LedgerAccount::where('code', $code)->value('cached_balance') ?? 0);
}

test('analyzer uses integer math for cost, consumption and the strict thirty percent rule', function () {
    $a = new FuelConsumptionAnalyzer;

    expect($a->totalCostIdr(50_000, 15_000))->toBe(750_000)
        ->and($a->totalCostIdr(33_333, 15_000))->toBe(499_995)
        ->and($a->kmPerLiterX100(300_000, 50_000))->toBe(600)
        ->and($a->kmPerLiterX100(0, 50_000))->toBe(0);

    expect($a->evaluate(420, [600, 600]))->toMatchArray(['baseline' => 600, 'deviation_bp' => -3000, 'is_anomaly' => false]) // tepat -30% bukan anomali
        ->and($a->evaluate(419, [600, 600])['is_anomaly'])->toBeTrue()
        ->and($a->evaluate(781, [600, 600])['is_anomaly'])->toBeTrue()
        ->and($a->evaluate(780, [600, 600])['is_anomaly'])->toBeFalse()
        ->and($a->evaluate(100, [600])['is_anomaly'])->toBeFalse(); // baseline belum cukup
});

test('(a) a fill records cost, distance, consumption, truck odometer and posts the fuel expense', function () {
    ($this->fill)(1000, 50);
    $log = ($this->fill)(1300, 50);

    expect($log->total_cost_idr)->toBe(750_000)
        ->and($log->distance_m)->toBe(300_000)
        ->and($log->km_per_liter_x100)->toBe(600)
        ->and($log->is_anomaly)->toBeFalse()
        ->and($this->truck->fresh()->odometer_m)->toBe(1_300_000)
        ->and(fuelBal(LogisticsLedger::FUEL_EXPENSE))->toBe(-1_500_000);
});

test('anomalies above thirty percent are flagged and excluded from the baseline', function () {
    ($this->fill)(1000, 50);
    foreach ([1300, 1600, 1900] as $km) {
        expect(($this->fill)($km, 50)->is_anomaly)->toBeFalse();
    }

    $thirsty = ($this->fill)(2200, 80); // 3,75 km/l vs 6,00
    expect($thirsty->is_anomaly)->toBeTrue()->and($thirsty->deviation_bp)->toBe(-3750)->and($thirsty->anomaly_note)->toContain('boros');

    $suspiciouslyEfficient = ($this->fill)(2500, 25); // 12 km/l
    expect($suspiciouslyEfficient->is_anomaly)->toBeTrue()->and($suspiciouslyEfficient->anomaly_note)->toContain('irit');

    $normal = ($this->fill)(2800, 50);
    expect($normal->is_anomaly)->toBeFalse()->and($normal->baseline_km_per_liter_x100)->toBe(600);
});

test('(b) the same odometer reading cannot be submitted twice', function () {
    ($this->fill)(1000, 50);
    ($this->fill)(1300, 50);

    expect(fn () => ($this->fill)(1300, 50))->toThrow(FuelLogException::class, 'Odometer harus lebih besar');
    expect(FuelLog::count())->toBe(2)->and(fuelBal(LogisticsLedger::FUEL_EXPENSE))->toBe(-1_500_000);
});

test('(c) ledger reconciles after fuel postings', function () {
    ($this->fill)(1000, 41.5, 14_850);
    ($this->fill)(1250, 40.25, 14_850);

    $this->artisan('bank:reconcile')->assertSuccessful();
});

test('(d) invalid amounts and decreasing odometers are rejected before anything is posted', function () {
    expect(fn () => app(RecordFuelLogAction::class)->execute($this->world->dispatcher, $this->truck, 0, 15_000, 1_000_000))->toThrow(FuelLogException::class, 'lebih dari nol')
        ->and(fn () => app(RecordFuelLogAction::class)->execute($this->world->dispatcher, $this->truck, 1_000, 0, 1_000_000))->toThrow(FuelLogException::class);

    $this->truck->update(['odometer_m' => 5_000_000]);
    expect(fn () => ($this->fill)(4000, 50))->toThrow(FuelLogException::class, 'Odometer harus lebih besar');
    expect(FuelLog::count())->toBe(0)->and(fuelBal(LogisticsLedger::FUEL_EXPENSE))->toBe(0);
});

test('(e) drivers record fuel only for the truck of their own active trip and staff for any truck', function () {
    $driver = $this->world->driver();
    $other = $this->world->driver();

    expect(fn () => ($this->fill)(1000, 50, 15_000, $driver->user, $driver))->toThrow(FuelLogException::class, 'tidak berwenang');

    Schedule::create([
        'schedule_number' => 'TRP-FUEL-1', 'mode' => TransportMode::ROAD, 'asset_type' => Truck::class, 'asset_id' => $this->truck->id, 'driver_id' => $driver->id,
        'origin_location_id' => $this->world->hubA->id, 'destination_location_id' => $this->world->hubB->id,
        'etd' => now()->addHour(), 'eta' => now()->addHours(3), 'cutoff_at' => now()->addMinutes(30), 'status' => ScheduleStatus::Scheduled,
        'cap_weight_kg' => '5000.000', 'cap_volume_dm3' => 14000,
    ]);

    $log = ($this->fill)(1000, 50, 15_000, $driver->user, $driver);
    expect($log->driver_id)->toBe($driver->id)->and($log->schedule_id)->not->toBeNull();
    expect(fn () => ($this->fill)(1300, 50, 15_000, $other->user, $other))->toThrow(FuelLogException::class);
    expect(fn () => ($this->fill)(1300, 50, 15_000, $other->user, $driver))->toThrow(FuelLogException::class); // tidak boleh mengatasnamakan driver lain
});

test('(e) http: page access by role, anomaly filter and validation', function () {
    ($this->fill)(1000, 50);
    foreach (['dispatcher', 'logistics_admin', 'admin', 'driver'] as $role) {
        $this->actingAs($this->world->user($role))->get(route('logistics.fuel.index'))->assertOk();
    }
    foreach (['shipper', 'hub_operator', 'customer'] as $role) {
        $this->actingAs($this->world->user($role))->get(route('logistics.fuel.index'))->assertForbidden();
        $this->actingAs($this->world->user($role))->post(route('logistics.fuel.store'), [])->assertForbidden();
    }

    $this->actingAs($this->world->dispatcher)->post(route('logistics.fuel.store'), [
        'truck_id' => $this->truck->id, 'liters' => 50.5, 'price_per_liter_idr' => 15000, 'odometer_km' => 1300.4,
    ])->assertSessionHas('success');
    expect(FuelLog::latest('id')->first()->liters_x1000)->toBe(50_500);

    $this->actingAs($this->world->dispatcher)->post(route('logistics.fuel.store'), ['truck_id' => $this->truck->id, 'liters' => 0, 'price_per_liter_idr' => 1, 'odometer_km' => 1])->assertSessionHasErrors('liters');
    $this->actingAs($this->world->dispatcher)->get(route('logistics.fuel.index', ['anomaly' => 1]))->assertOk();
});
