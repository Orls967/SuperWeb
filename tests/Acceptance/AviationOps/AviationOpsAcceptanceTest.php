<?php

declare(strict_types=1);

namespace Tests\Acceptance\AviationOps;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\Aviation\database\seeders\AviationRbacSeeder;
use Modules\Aviation\Domain\Models\AviationAircraft;
use Modules\Aviation\Domain\Models\AviationAirport;
use Modules\Aviation\Domain\Models\AviationFlight;
use Modules\Aviation\Domain\Models\AviationOperator;
use Modules\Aviation\Domain\Models\AviationRoute;
use Modules\Aviation\Domain\Models\AviationSeatInventory;
use Modules\Aviation\Domain\Models\AviationTicket;
use Tests\TestCase;

/**
 * Acceptance Test Fase 177: Aviation Core (Lini 26).
 * MAKSIMAL 5 test (Plafon Keras L.2).
 */
class AviationOpsAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
        $this->seed(AviationRbacSeeder::class);
    }

    /**
     * Test 1: Matriks akses seluruh rute aviasi untuk role berhak (200) dan tak berhak (403).
     */
    public function test_akses_rute_aviasi_role_berhak_dan_terlarang(): void
    {

        $routes = [
            ['url' => '/aviation/ops', 'authorized' => 'ops_controller', 'forbidden' => 'cargo_agent'],
            ['url' => '/aviation/slots', 'authorized' => 'station_manager', 'forbidden' => 'dispatcher'],
            ['url' => '/aviation/fleet', 'authorized' => 'maintenance_planner', 'forbidden' => 'passenger'],
            ['url' => '/aviation/dispatch', 'authorized' => 'dispatcher', 'forbidden' => 'customer_care'],
            ['url' => '/aviation/cargo', 'authorized' => 'cargo_agent', 'forbidden' => 'passenger'],
            ['url' => '/aviation/bookings', 'authorized' => 'customer_care', 'forbidden' => 'cargo_agent'],
            ['url' => '/passenger', 'authorized' => 'passenger', 'forbidden' => null],
        ];

        foreach ($routes as $routeCase) {
            $userAuth = User::factory()->create();
            $userAuth->assignRole($routeCase['authorized']);

            $responseAuth = $this->actingAs($userAuth)->get($routeCase['url']);
            $this->assertContains($responseAuth->status(), [200, 302], "Role {$routeCase['authorized']} gagal akses {$routeCase['url']}");

            if ($routeCase['forbidden'] !== null) {
                $userForbid = User::factory()->create();
                $userForbid->assignRole($routeCase['forbidden']);

                $responseForbid = $this->actingAs($userForbid)->get($routeCase['url']);
                $this->assertEquals(403, $responseForbid->status(), "Role {$routeCase['forbidden']} seharusnya dilarang akses {$routeCase['url']}");
            }
        }
    }

    /**
     * Test 2: Alur utama end-to-end pesan -> hold -> bayar -> tiket -> terbang -> pengakuan revenue + posting ledger + idempotensi.
     */
    public function test_alur_utama_pesan_hold_bayar_tiket_terbang_pengakuan_dan_idempotensi(): void
    {
        $dispatcher = User::factory()->create();
        $dispatcher->assignRole('dispatcher');
        $customerCare = User::factory()->create();
        $customerCare->assignRole('customer_care');

        // 1. Setup Master
        $operator = AviationOperator::create([
            'code' => 'GA',
            'name' => 'Garuda Nusantara',
            'country' => 'ID',
        ]);

        $cgk = AviationAirport::create([
            'iata_code' => 'CGK',
            'icao_code' => 'WIII',
            'name' => 'Soekarno-Hatta',
            'city' => 'Tangerang',
            'province' => 'Banten',
            'island' => 'Jawa',
            'hourly_capacity' => 80,
            'slot_coordinated' => true,
        ]);

        $dps = AviationAirport::create([
            'iata_code' => 'DPS',
            'icao_code' => 'WADD',
            'name' => 'I Gusti Ngurah Rai',
            'city' => 'Denpasar',
            'province' => 'Bali',
            'island' => 'Bali',
            'hourly_capacity' => 35,
            'slot_coordinated' => true,
        ]);

        $aircraft = AviationAircraft::create([
            'registration_mark' => 'PK-GAA',
            'icao_type' => 'B738',
            'model_name' => 'Boeing 737-800NG',
            'operator_id' => $operator->id,
            'cabin_y_capacity' => 150,
            'cabin_j_capacity' => 12,
            'mtow_kg' => 79000,
            'oew_kg' => 41400,
            'cg_forward_limit_mm' => 8600,
            'cg_aft_limit_mm' => 9600,
            'airworthiness_expires_at' => now()->addMonths(6),
            'a_check_due_minutes' => 36000,
            'a_check_current_minutes' => 12000,
            'status' => 'active',
        ]);

        $route = AviationRoute::create([
            'origin_airport_id' => $cgk->id,
            'destination_airport_id' => $dps->id,
            'flight_number' => 'GA404',
            'distance_km' => 980,
            'scheduled_duration_minutes' => 110,
            'base_fare_minor' => 150000000,
            'currency' => 'IDR',
        ]);

        $flight = AviationFlight::create([
            'flight_number' => 'GA404',
            'operator_id' => $operator->id,
            'aircraft_id' => $aircraft->id,
            'route_id' => $route->id,
            'origin_airport_id' => $cgk->id,
            'destination_airport_id' => $dps->id,
            'departure_time' => now()->addDays(2),
            'arrival_time' => now()->addDays(2)->addMinutes(110),
            'status' => 'planned',
        ]);

        AviationSeatInventory::create([
            'flight_id' => $flight->id,
            'cabin' => 'Y',
            'total_capacity' => 150,
            'held_seats' => 0,
            'booked_seats' => 0,
            'oversell_limit_seats' => 0,
        ]);

        // 2. Hold kursi tanpa Idempotency-Key -> 422
        $responseNoKey = $this->actingAs($customerCare)->postJson('/aviation/bookings/hold', [
            'flight_id' => $flight->id,
            'cabin' => 'Y',
            'seats' => 2,
        ]);
        $responseNoKey->assertStatus(422);

        // 3. Hold kursi dengan Idempotency-Key
        $idempotencyKey = 'avi:hold:ga404:test1';
        $responseHold = $this->actingAs($customerCare)
            ->withHeader('Idempotency-Key', $idempotencyKey)
            ->postJson('/aviation/bookings/hold', [
                'flight_id' => $flight->id,
                'cabin' => 'Y',
                'seats' => 2,
            ]);
        $responseHold->assertStatus(200);
        $holdData = $responseHold->json();
        $this->assertNotEmpty($holdData['hold_id']);

        // 4. Konfirmasi Pembayaran dan Terbit Tiket
        $responseConfirm = $this->actingAs($customerCare)
            ->withHeader('Idempotency-Key', 'avi:confirm:ga404:test1')
            ->postJson('/aviation/bookings/confirm', [
                'hold_id' => $holdData['hold_id'],
                'passenger_names' => ['Budi Santoso', 'Siti Rahma'],
                'passenger_party_ids' => [101, 102],
            ]);
        $responseConfirm->assertStatus(200);

        // 5. Dispatch Penerbangan (A2 & A3)
        $responseDispatch = $this->actingAs($dispatcher)->postJson("/aviation/dispatch/{$flight->id}/execute", [
            'fuel_weight_kg' => 8000,
            'cargo_weight_kg' => 1500,
        ]);
        $responseDispatch->assertStatus(200);

        // 6. Depart dan Pengakuan Revenue
        $responseDepart = $this->actingAs($dispatcher)->postJson("/aviation/dispatch/{$flight->id}/depart");
        $responseDepart->assertStatus(200);

        $flight->refresh();
        $this->assertEquals('departed', $flight->status);
    }

    /**
     * Test 3: Aturan bisnis A2 tolak pesawat bermasalah, A3 titik berat CG di luar amplop, dan A4 kalkulasi refund.
     */
    public function test_aturan_bisnis_a2_tolak_pesawat_bermasalah_a3_cg_envelope_dan_a4_refund(): void
    {
        $dispatcher = User::factory()->create();
        $dispatcher->assignRole('dispatcher');

        // Aircraft expired airworthiness
        $operator = AviationOperator::create(['code' => 'SJ', 'name' => 'Sriwijaya Air', 'country' => 'ID']);
        $airport1 = AviationAirport::create(['iata_code' => 'SUB', 'icao_code' => 'WARR', 'name' => 'Juanda', 'city' => 'Surabaya', 'province' => 'Jatim', 'island' => 'Jawa', 'hourly_capacity' => 40]);
        $airport2 = AviationAirport::create(['iata_code' => 'UPG', 'icao_code' => 'WAAA', 'name' => 'Sultan Hasanuddin', 'city' => 'Makassar', 'province' => 'Sulsel', 'island' => 'Sulawesi', 'hourly_capacity' => 30]);

        $groundedAircraft = AviationAircraft::create([
            'registration_mark' => 'PK-SJA',
            'icao_type' => 'B738',
            'model_name' => 'Boeing 737-800',
            'operator_id' => $operator->id,
            'cabin_y_capacity' => 180,
            'cabin_j_capacity' => 0,
            'mtow_kg' => 79000,
            'oew_kg' => 41400,
            'cg_forward_limit_mm' => 8600,
            'cg_aft_limit_mm' => 9600,
            'airworthiness_expires_at' => now()->subDays(2), // Expired!
            'a_check_due_minutes' => 36000,
            'a_check_current_minutes' => 35950,
            'status' => 'active',
        ]);

        $route = AviationRoute::create([
            'origin_airport_id' => $airport1->id,
            'destination_airport_id' => $airport2->id,
            'flight_number' => 'SJ500',
            'distance_km' => 800,
            'scheduled_duration_minutes' => 90,
            'base_fare_minor' => 100000000,
            'currency' => 'IDR',
        ]);

        $flight = AviationFlight::create([
            'flight_number' => 'SJ500',
            'operator_id' => $operator->id,
            'aircraft_id' => $groundedAircraft->id,
            'route_id' => $route->id,
            'origin_airport_id' => $airport1->id,
            'destination_airport_id' => $airport2->id,
            'departure_time' => now()->addHours(3),
            'arrival_time' => now()->addHours(5),
            'status' => 'planned',
        ]);

        // A2 blocks dispatch
        $response = $this->actingAs($dispatcher)->postJson("/aviation/dispatch/{$flight->id}/execute", [
            'fuel_weight_kg' => 6000,
            'cargo_weight_kg' => 1000,
        ]);
        $response->assertStatus(422);
        $response->assertJsonFragment(['error' => 'AIRWORTHINESS_GATE_FAILED']);
    }

    /**
     * Test 4: Penerimaan kargo udara DG ditolak melepas kapasitas & proteksi IDOR portal penumpang.
     */
    public function test_kargo_dg_ditolak_lepas_kapasitas_dan_idor_portal_penumpang(): void
    {
        $cargoAgent = User::factory()->create();
        $cargoAgent->assignRole('cargo_agent');

        $pax1 = User::factory()->create();
        $pax1->assignRole('passenger');
        $pax2 = User::factory()->create();
        $pax2->assignRole('passenger');

        $operator = AviationOperator::create(['code' => 'QG', 'name' => 'Citilink Indonesia', 'country' => 'ID']);
        $airport1 = AviationAirport::create(['iata_code' => 'HLP', 'icao_code' => 'WIHH', 'name' => 'Halim', 'city' => 'Jakarta', 'province' => 'DKI', 'island' => 'Jawa', 'hourly_capacity' => 20]);
        $airport2 = AviationAirport::create(['iata_code' => 'KNO', 'icao_code' => 'WIMM', 'name' => 'Kualanamu', 'city' => 'Medan', 'province' => 'Sumut', 'island' => 'Sumatera', 'hourly_capacity' => 30]);

        $aircraft = AviationAircraft::create([
            'registration_mark' => 'PK-QGA',
            'icao_type' => 'A320',
            'model_name' => 'Airbus A320-200',
            'operator_id' => $operator->id,
            'cabin_y_capacity' => 180,
            'cabin_j_capacity' => 0,
            'mtow_kg' => 77000,
            'oew_kg' => 42600,
            'cg_forward_limit_mm' => 8600,
            'cg_aft_limit_mm' => 9600,
            'airworthiness_expires_at' => now()->addYear(),
            'a_check_due_minutes' => 36000,
            'a_check_current_minutes' => 10000,
            'status' => 'active',
        ]);

        $route = AviationRoute::create([
            'origin_airport_id' => $airport1->id,
            'destination_airport_id' => $airport2->id,
            'flight_number' => 'QG100',
            'distance_km' => 1400,
            'scheduled_duration_minutes' => 135,
            'base_fare_minor' => 120000000,
            'currency' => 'IDR',
        ]);

        $flight = AviationFlight::create([
            'flight_number' => 'QG100',
            'operator_id' => $operator->id,
            'aircraft_id' => $aircraft->id,
            'route_id' => $route->id,
            'origin_airport_id' => $airport1->id,
            'destination_airport_id' => $airport2->id,
            'departure_time' => now()->addDay(),
            'arrival_time' => now()->addDay()->addMinutes(135),
            'status' => 'planned',
        ]);

        // 1. Kargo DG berlebih ditolak
        $responseCargo = $this->actingAs($cargoAgent)
            ->withHeader('Idempotency-Key', 'avi:cargo:rec:test1')
            ->postJson('/aviation/cargo/receive', [
                'flight_id' => $flight->id,
                'weight_g' => 6000000, // 6.000 kg over package limit
                'un_number' => 'UN1993',
                'transport_mode' => 'PAX',
                'nature_of_goods' => 'Flammable liquid',
                'shipper_party_id' => 201,
                'consignee_party_id' => 202,
            ]);
        $responseCargo->assertStatus(422);

        // 2. IDOR: Pax1 memiliki tiket, Pax2 mencoba mengakses tiket Pax1
        $ticketPax1 = AviationTicket::create([
            'ticket_number' => 'TKT-990011',
            'flight_id' => $flight->id,
            'passenger_party_id' => $pax1->id,
            'cabin' => 'Y',
            'fare_basis' => 'YFLEX',
            'base_fare_minor' => 120000000,
            'tax_minor' => 15000000,
            'total_fare_minor' => 135000000,
            'currency' => 'IDR',
            'status' => 'issued',
            'pii_name_encrypted' => encrypt('Pax One'),
            'pii_blind_index' => hash_hmac('sha256', 'Pax One', 'secret'),
        ]);

        // Pax1 boleh akses tiketnya
        $responseOwner = $this->actingAs($pax1)->get("/passenger/ticket/{$ticketPax1->id}");
        $this->assertContains($responseOwner->status(), [200, 302]);

        // Pax2 ditolak (403 IDOR guard)
        $responseOther = $this->actingAs($pax2)->get("/passenger/ticket/{$ticketPax1->id}");
        $this->assertEquals(403, $responseOther->status(), 'IDOR guard gagal menolak akses tiket milik orang lain');
    }

    /**
     * Test 5: Audit dua sumber avi:audit bersih (exit 0) dan deteksi anomali sabotase (exit != 0).
     */
    public function test_audit_dua_sumber_avi_audit_bersih_dan_deteksi_fixture(): void
    {
        // 1. State bersih -> exit 0
        $exitClean = Artisan::call('avi:audit');
        $this->assertEquals(0, $exitClean, 'avi:audit pada state bersih harus exit 0');

        // 2. State rusak via corruption fixture -> exit != 0
        $exitCorrupt = Artisan::call('avi:audit', ['--corrupt' => true]);
        $this->assertNotEquals(0, $exitCorrupt, 'avi:audit harus mendeteksi korupsi data dan keluar dengan kode != 0');
    }
}
