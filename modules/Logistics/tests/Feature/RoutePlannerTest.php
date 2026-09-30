<?php

declare(strict_types=1);

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Logistics\Application\Actions\PlanShipmentRouteAction;
use Modules\Logistics\Domain\Enums\LocationType;
use Modules\Logistics\Domain\Enums\PaymentTerms;
use Modules\Logistics\Domain\Enums\ScheduleStatus;
use Modules\Logistics\Domain\Enums\ServiceLevel;
use Modules\Logistics\Domain\Enums\ShipmentStatus;
use Modules\Logistics\Domain\Enums\TransportMode;
use Modules\Logistics\Domain\Models\Location;
use Modules\Logistics\Domain\Models\Package;
use Modules\Logistics\Domain\Models\Schedule;
use Modules\Logistics\Domain\Models\Shipment;
use Modules\Logistics\Domain\Models\ShipmentLeg;
use Modules\Logistics\Domain\Services\RoutePlanner\RouteEdge;
use Modules\Logistics\Domain\Services\RoutePlanner\RouteGraph;
use Modules\Logistics\Domain\Services\RoutePlanner\RouteNode;
use Modules\Logistics\Domain\Services\RoutePlanner\RoutePlanner;
use Modules\Logistics\Domain\Services\RoutePlanner\RouteRequest;
use Modules\Logistics\Domain\ValueObjects\TrackingNumber;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hubBdj = Location::create([
        'code' => 'HUB-BDJ',
        'name' => 'Banjarmasin Central Hub',
        'type' => LocationType::HUB,
        'city' => 'Banjarmasin',
        'province' => 'Kalimantan Selatan',
        'country_code' => 'ID',
        'lat_e6' => -3319400,
        'lng_e6' => 114590800,
        'timezone' => 'Asia/Makassar',
        'min_connection_minutes' => 60,
    ]);

    $this->airportBdj = Location::create([
        'code' => 'AIRP-BDJ',
        'name' => 'Bandara Syamsudin Noor',
        'type' => LocationType::AIRPORT,
        'city' => 'Banjarbaru',
        'province' => 'Kalimantan Selatan',
        'country_code' => 'ID',
        'lat_e6' => -3440000,
        'lng_e6' => 114750000,
        'timezone' => 'Asia/Makassar',
        'min_connection_minutes' => 90,
    ]);

    $this->airportCgk = Location::create([
        'code' => 'AIRP-CGK',
        'name' => 'Bandara Soekarno-Hatta',
        'type' => LocationType::AIRPORT,
        'city' => 'Tangerang',
        'province' => 'Banten',
        'country_code' => 'ID',
        'lat_e6' => -6125600,
        'lng_e6' => 106655900,
        'timezone' => 'Asia/Jakarta',
        'min_connection_minutes' => 90,
    ]);

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
});

test('route planner finds multimodal path and enforces intermediate minimum connection time', function () {
    $graph = new RouteGraph;
    $graph->addNode(new RouteNode(1, 'HUB-BDJ', 'Banjarmasin', 60));
    $graph->addNode(new RouteNode(2, 'AIRP-BDJ', 'Bandara BDJ', 90)); // 90 min transfer required
    $graph->addNode(new RouteNode(3, 'AIRP-CGK', 'Bandara CGK', 90));

    $baseTime = Carbon::parse('2026-10-01 08:00:00');

    // Leg 1: Hub BDJ -> Airport BDJ (Road feeder: 08:30 to 09:30)
    $graph->addEdge(new RouteEdge(
        scheduleId: 101,
        scheduleNumber: 'FDR-01',
        originLocationId: 1,
        destinationLocationId: 2,
        mode: TransportMode::ROAD,
        etd: $baseTime->copy()->addMinutes(30),
        eta: $baseTime->copy()->addMinutes(90), // Arrives 09:30
        cutoffAt: $baseTime->copy()->addMinutes(20),
        costIdr: 25000
    ));

    // Flight A: Departs 10:15 (only 45 min after 09:30 arrival -> FAILS min connection of 90 min)
    $graph->addEdge(new RouteEdge(
        scheduleId: 102,
        scheduleNumber: 'FLT-EARLY',
        originLocationId: 2,
        destinationLocationId: 3,
        mode: TransportMode::AIR,
        etd: $baseTime->copy()->addMinutes(135), // 10:15
        eta: $baseTime->copy()->addMinutes(255), // 12:15
        cutoffAt: $baseTime->copy()->addMinutes(105),
        costIdr: 150000
    ));

    // Flight B: Departs 11:30 (120 min after 09:30 arrival -> PASSES min connection of 90 min)
    $graph->addEdge(new RouteEdge(
        scheduleId: 103,
        scheduleNumber: 'FLT-VALID',
        originLocationId: 2,
        destinationLocationId: 3,
        mode: TransportMode::AIR,
        etd: $baseTime->copy()->addMinutes(210), // 11:30
        eta: $baseTime->copy()->addMinutes(330), // 13:30
        cutoffAt: $baseTime->copy()->addMinutes(150),
        costIdr: 120000
    ));

    $planner = new RoutePlanner;
    $request = new RouteRequest(
        originLocationId: 1,
        destinationLocationId: 3,
        serviceLevel: ServiceLevel::Express,
        readyAt: $baseTime
    );

    $routes = $planner->findRoutes($graph, $request);

    expect($routes)->toHaveCount(1)
        ->and($routes[0]->legCount())->toBe(2)
        ->and($routes[0]->legs[0]->scheduleNumber)->toBe('FDR-01')
        ->and($routes[0]->legs[1]->scheduleNumber)->toBe('FLT-VALID')
        ->and($routes[0]->arrivalTime->equalTo($baseTime->copy()->addMinutes(330)))->toBeTrue();
});

test('route planner filters transport modes according to service level', function () {
    $graph = new RouteGraph;
    $graph->addNode(new RouteNode(1, 'ORIGIN', 'Origin', 30));
    $graph->addNode(new RouteNode(2, 'DEST', 'Destination', 30));

    $baseTime = Carbon::parse('2026-10-01 08:00:00');

    // Edge 1: Air flight
    $graph->addEdge(new RouteEdge(
        scheduleId: 1,
        scheduleNumber: 'AIR-01',
        originLocationId: 1,
        destinationLocationId: 2,
        mode: TransportMode::AIR,
        etd: $baseTime->copy()->addHours(1),
        eta: $baseTime->copy()->addHours(3),
        cutoffAt: $baseTime->copy()->addMinutes(30)
    ));

    // Edge 2: Sea vessel
    $graph->addEdge(new RouteEdge(
        scheduleId: 2,
        scheduleNumber: 'SEA-01',
        originLocationId: 1,
        destinationLocationId: 2,
        mode: TransportMode::SEA,
        etd: $baseTime->copy()->addHours(2),
        eta: $baseTime->copy()->addHours(20),
        cutoffAt: $baseTime->copy()->addHour()
    ));

    $planner = new RoutePlanner;

    // Express request: Air only
    $expressReq = new RouteRequest(1, 2, ServiceLevel::Express, $baseTime);
    $expressRoutes = $planner->findRoutes($graph, $expressReq);
    expect($expressRoutes)->toHaveCount(1)
        ->and($expressRoutes[0]->legs[0]->mode)->toBe(TransportMode::AIR);

    // Economy request: Sea only
    $economyReq = new RouteRequest(1, 2, ServiceLevel::Economy, $baseTime);
    $economyRoutes = $planner->findRoutes($graph, $economyReq);
    expect($economyRoutes)->toHaveCount(1)
        ->and($economyRoutes[0]->legs[0]->mode)->toBe(TransportMode::SEA);
});

test('route planner enforces dangerous goods and reefer restrictions', function () {
    $graph = new RouteGraph;
    $graph->addNode(new RouteNode(1, 'A', 'Node A', 30));
    $graph->addNode(new RouteNode(2, 'B', 'Node B', 30));

    $baseTime = Carbon::parse('2026-10-01 08:00:00');

    // Air edge (strictly rejects DG Class 1 explosives)
    $graph->addEdge(new RouteEdge(
        scheduleId: 1,
        scheduleNumber: 'AIR-EXP',
        originLocationId: 1,
        destinationLocationId: 2,
        mode: TransportMode::AIR,
        etd: $baseTime->copy()->addHours(2),
        eta: $baseTime->copy()->addHours(4),
        cutoffAt: $baseTime->copy()->addHour(),
        isReeferCapable: false
    ));

    // Road edge with reefer capability
    $graph->addEdge(new RouteEdge(
        scheduleId: 2,
        scheduleNumber: 'TRK-REEFER',
        originLocationId: 1,
        destinationLocationId: 2,
        mode: TransportMode::ROAD,
        etd: $baseTime->copy()->addHours(1),
        eta: $baseTime->copy()->addHours(6),
        cutoffAt: $baseTime->copy()->addMinutes(30),
        isReeferCapable: true
    ));

    $planner = new RoutePlanner;

    // 1. DG Class 1 on Express (Air) must be rejected on air leg and fallback to road
    $dgRequest = new RouteRequest(
        originLocationId: 1,
        destinationLocationId: 2,
        serviceLevel: ServiceLevel::Express,
        readyAt: $baseTime,
        dgClass: '1'
    );
    $dgRoutes = $planner->findRoutes($graph, $dgRequest);
    expect($dgRoutes)->toHaveCount(1)
        ->and($dgRoutes[0]->legs[0]->scheduleNumber)->toBe('TRK-REEFER');

    // 2. Reefer cargo must only take reefer capable edges
    $reeferRequest = new RouteRequest(
        originLocationId: 1,
        destinationLocationId: 2,
        serviceLevel: ServiceLevel::Express,
        readyAt: $baseTime,
        isReefer: true
    );
    $reeferRoutes = $planner->findRoutes($graph, $reeferRequest);
    expect($reeferRoutes)->toHaveCount(1)
        ->and($reeferRoutes[0]->legs[0]->isReeferCapable)->toBeTrue();
});

test('plan shipment route action persists itinerary legs to lgx_shipment_legs', function () {
    // Create DB Schedules
    $sched1 = Schedule::create([
        'schedule_number' => 'TRP-LEG-1',
        'mode' => TransportMode::ROAD,
        'origin_location_id' => $this->hubBdj->id,
        'destination_location_id' => $this->airportBdj->id,
        'etd' => now()->addHours(2),
        'eta' => now()->addHours(3),
        'cutoff_at' => now()->addHour(),
        'status' => ScheduleStatus::Scheduled,
        'cap_weight_kg' => '5000.000',
        'cap_volume_dm3' => 15000,
    ]);

    $sched2 = Schedule::create([
        'schedule_number' => 'FLT-LEG-2',
        'mode' => TransportMode::AIR,
        'origin_location_id' => $this->airportBdj->id,
        'destination_location_id' => $this->airportCgk->id,
        'etd' => now()->addHours(5), // 2 hours after arrival (min connection is 90 mins)
        'eta' => now()->addHours(7),
        'cutoff_at' => now()->addHours(4),
        'status' => ScheduleStatus::Scheduled,
        'cap_weight_kg' => '20000.000',
        'cap_volume_dm3' => 80000,
    ]);

    $user = User::factory()->create();
    $shipment = Shipment::create([
        'tracking_number' => TrackingNumber::generate(),
        'shipper_id' => $user->id,
        'consignee_name' => 'John Doe',
        'consignee_phone' => '081234567890',
        'consignee_address' => ['street' => 'Jl. Thamrin', 'city' => 'Jakarta'],
        'origin_location_id' => $this->hubBdj->id,
        'destination_location_id' => $this->airportCgk->id,
        'service_level' => ServiceLevel::Express,
        'mode' => TransportMode::AIR,
        'payment_terms' => PaymentTerms::Prepaid,
        'status' => ShipmentStatus::Booked,
        'total_chargeable_weight_g' => 5000,
        'total_amount_idr' => 250000,
        'booked_at' => now(),
    ]);

    Package::create([
        'shipment_id' => $shipment->id,
        'weight_g' => 5000,
        'length_mm' => 200,
        'width_mm' => 200,
        'height_mm' => 200,
        'description' => 'Paket Express Jakarta',
    ]);

    $action = app(PlanShipmentRouteAction::class);
    $itineraries = $action->execute($shipment, persistBestItinerary: true);

    expect($itineraries)->not->toBeEmpty();

    $persistedLegs = ShipmentLeg::where('shipment_id', $shipment->id)
        ->orderBy('leg_sequence')
        ->get();

    expect($persistedLegs)->toHaveCount(2)
        ->and($persistedLegs[0]->leg_sequence)->toBe(1)
        ->and($persistedLegs[0]->schedule_id)->toBe($sched1->id)
        ->and($persistedLegs[0]->origin_location_id)->toBe($this->hubBdj->id)
        ->and($persistedLegs[1]->leg_sequence)->toBe(2)
        ->and($persistedLegs[1]->schedule_id)->toBe($sched2->id)
        ->and($persistedLegs[1]->destination_location_id)->toBe($this->airportCgk->id);
});

test('route planner benchmark performance: 300 locations and 10000 schedules in under 300 ms', function () {
    $graph = new RouteGraph;
    $locationCount = 300;
    $scheduleCount = 10000;

    // 1. Generate 300 Location nodes
    for ($i = 1; $i <= $locationCount; $i++) {
        $graph->addNode(new RouteNode(
            locationId: $i,
            code: "LOC-{$i}",
            name: "Location {$i}",
            minConnectionMinutes: 45
        ));
    }

    $baseTime = Carbon::parse('2026-10-01 00:00:00');

    // 2. Generate 10,000 synthetic schedules distributed across network
    // Create direct and multi-hop paths from 1 to 300
    for ($j = 1; $j <= $scheduleCount; $j++) {
        $orig = ($j % ($locationCount - 1)) + 1;
        $dest = (($orig + ($j % 15)) % $locationCount) + 1;
        if ($orig === $dest) {
            $dest = ($dest % $locationCount) + 1;
        }

        $departHour = ($j % 48) + 1;
        $durationHours = ($j % 6) + 2;

        $etd = $baseTime->copy()->addHours($departHour);
        $eta = $etd->copy()->addHours($durationHours);

        $graph->addEdge(new RouteEdge(
            scheduleId: $j,
            scheduleNumber: "SCHED-{$j}",
            originLocationId: $orig,
            destinationLocationId: $dest,
            mode: ($j % 3 === 0) ? TransportMode::AIR : TransportMode::ROAD,
            etd: $etd,
            eta: $eta,
            cutoffAt: $etd->copy()->subHour(),
            costIdr: ($j % 50) * 10000 + 50000,
            isReeferCapable: ($j % 4 === 0)
        ));
    }

    // Ensure at least one guaranteed path exists from 1 to 300
    $graph->addEdge(new RouteEdge(
        scheduleId: 99991,
        scheduleNumber: 'GUARANTEED-HOP-1',
        originLocationId: 1,
        destinationLocationId: 50,
        mode: TransportMode::ROAD,
        etd: $baseTime->copy()->addHours(2),
        eta: $baseTime->copy()->addHours(5),
        cutoffAt: $baseTime->copy()->addHour(),
        costIdr: 50000
    ));

    $graph->addEdge(new RouteEdge(
        scheduleId: 99992,
        scheduleNumber: 'GUARANTEED-HOP-2',
        originLocationId: 50,
        destinationLocationId: 300,
        mode: TransportMode::AIR,
        etd: $baseTime->copy()->addHours(7), // 2 hrs transfer > 45 mins
        eta: $baseTime->copy()->addHours(9),
        cutoffAt: $baseTime->copy()->addHours(6),
        costIdr: 150000
    ));

    expect($graph->nodeCount())->toBe(300);
    expect($graph->edgeCount())->toBeGreaterThanOrEqual(10000);

    $planner = new RoutePlanner;
    $request = new RouteRequest(
        originLocationId: 1,
        destinationLocationId: 300,
        serviceLevel: ServiceLevel::Express,
        readyAt: $baseTime
    );

    // 3. Measure Execution Time
    $startTime = microtime(true);
    $results = $planner->findRoutes($graph, $request, maxResults: 5);
    $elapsedMs = (microtime(true) - $startTime) * 1000;

    expect($results)->not->toBeEmpty();
    expect($elapsedMs)->toBeLessThan(300.0); // Strictly asserts < 300 ms!
});
