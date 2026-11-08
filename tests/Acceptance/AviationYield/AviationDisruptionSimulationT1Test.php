<?php

declare(strict_types=1);

namespace Tests\Acceptance\AviationYield;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Aviation\Application\Sagas\DisruptionIrropsSaga;
use Modules\Aviation\Application\Services\AviationAirportDisruptionCoordinationService;
use Modules\Aviation\Application\Services\AviationFlightTelemetryIntegrationService;
use Modules\Aviation\Application\Services\AviationIrropsCommunicationService;
use Modules\Aviation\Domain\Models\AviationAirport;
use Modules\Aviation\Domain\Models\AviationFlight;
use Modules\Aviation\Domain\Models\AviationOperator;
use Modules\Aviation\Domain\Models\AviationParametricClaim;
use Modules\Aviation\Domain\Models\AviationRoute;
use Modules\Aviation\Domain\Models\AviationTicket;
use Tests\TestCase;

class AviationDisruptionSimulationT1Test extends TestCase
{
    use RefreshDatabase;

    public function test_volcanic_eruption_dps_vona_red_crisis_simulation(): void
    {
        // 1. Setup Maskapai & Bandara
        $operator = AviationOperator::create([
            'code' => 'GA',
            'name' => 'Garuda Indonesia',
            'country' => 'ID',
            'status' => 'active',
        ]);

        $dps = AviationAirport::create([
            'iata_code' => 'DPS',
            'icao_code' => 'WADD',
            'name' => 'I Gusti Ngurah Rai Int',
            'city' => 'Denpasar',
            'province' => 'Bali',
            'island' => 'Bali',
            'slot_coordinated' => true,
        ]);

        $cgk = AviationAirport::create([
            'iata_code' => 'CGK',
            'icao_code' => 'WIII',
            'name' => 'Soekarno-Hatta Int',
            'city' => 'Jakarta',
            'province' => 'DKI Jakarta',
            'island' => 'Jawa',
            'slot_coordinated' => true,
        ]);

        $routeOutbound = AviationRoute::create([
            'origin_airport_id' => $dps->id,
            'destination_airport_id' => $cgk->id,
            'flight_number' => 'GA-401',
            'distance_km' => 980,
            'scheduled_duration_minutes' => 110,
            'base_fare_minor' => 150000000,
            'currency' => 'IDR',
            'status' => 'active',
        ]);

        $routeInbound = AviationRoute::create([
            'origin_airport_id' => $cgk->id,
            'destination_airport_id' => $dps->id,
            'flight_number' => 'GA-402',
            'distance_km' => 980,
            'scheduled_duration_minutes' => 110,
            'base_fare_minor' => 150000000,
            'currency' => 'IDR',
            'status' => 'active',
        ]);

        // 2. Setup 12 Penerbangan Terjadwal yang Melibatkan DPS
        $flights = [];
        for ($i = 1; $i <= 12; $i++) {
            $isFromDps = ($i % 2 === 0);
            $flt = AviationFlight::create([
                'operator_id' => $operator->id,
                'flight_number' => 'GA-'.(400 + $i),
                'route_id' => $isFromDps ? $routeOutbound->id : $routeInbound->id,
                'origin_airport_id' => $isFromDps ? $dps->id : $cgk->id,
                'destination_airport_id' => $isFromDps ? $cgk->id : $dps->id,
                'aircraft_id' => null,
                'status' => 'scheduled',
                'departure_time' => now()->addHours($i),
                'arrival_time' => now()->addHours($i + 2),
                'departure_slot_id' => null,
                'arrival_slot_id' => null,
                'fuel_burn_kg' => 0,
            ]);
            $flights[] = $flt;

            // Daftarkan tiket per penerbangan
            for ($j = 1; $j <= 5; $j++) {
                AviationTicket::create([
                    'flight_id' => $flt->id,
                    'ticket_number' => sprintf('TKT-%s-%03d', $flt->flight_number, $j),
                    'cabin' => 'Y',
                    'seat_assignment' => "{$j}A",
                    'fare_basis' => 'YSTD',
                    'base_fare_minor' => 150000000,
                    'tax_minor' => 15000000,
                    'total_fare_minor' => 165000000,
                    'currency' => 'IDR',
                    'status' => 'confirmed',
                    'pii_name_encrypted' => encrypt("Penumpang {$i}-{$j}"),
                    'pii_blind_index' => hash('sha256', "penumpang_{$i}_{$j}"),
                ]);
            }
        }

        // 3. Ingest NOTAM Penutupan Bandara DPS (VONA RED Gunung Agung)
        $telemetryService = app(AviationFlightTelemetryIntegrationService::class);
        $notamResult = $telemetryService->processAirportClosureNotam(
            airportIata: 'DPS',
            notamNumber: 'A1284/26',
            effectiveFrom: now()->subMinutes(10)->toDateTimeString(),
            effectiveUntil: now()->addHours(18)->toDateTimeString(),
            reason: 'VOLCANIC_ASH_GUNUNG_AGUNG_FL320'
        );

        $this->assertEquals('DPS', $notamResult['airport']);
        $this->assertGreaterThanOrEqual(12, $notamResult['impacted_flights_count']);

        // 4. Eksekusi Saga IRROPS untuk Penerbangan Utama
        $irropsSaga = app(DisruptionIrropsSaga::class);
        $sagaResult = $irropsSaga->execute([
            'flight_id' => $flights[0]->id,
            'incident_category' => 'FORCE_MAJEURE_VOLCANIC_ERUPTION',
            'delay_minutes' => 1080, // 18 jam
            'incident_description' => 'Erupsi Gunung Agung VONA RED, pembatalan massal',
        ]);

        $this->assertEquals('COMPLETED', $sagaResult['status']);
        $this->assertGreaterThan(0, $sagaResult['vouchers_dispatched']);
        $this->assertGreaterThan(0, $sagaResult['hotel_rooms']);

        // 5. Verifikasi Komunikasi & Koordinasi Darat
        $commService = app(AviationIrropsCommunicationService::class);
        $commRes = $commService->dispatchPassengerBroadcast($sagaResult['disruption_case_id']);
        $this->assertGreaterThan(0, $commRes['notifications_sent']);

        $groundService = app(AviationAirportDisruptionCoordinationService::class);
        $groundRes = $groundService->coordinateGroundResponse($sagaResult['disruption_case_id']);
        $this->assertGreaterThan(0, $groundRes['buses_requested']);

        // 6. Verifikasi Pencatatan Klaim Asuransi Parametrik G7-K29
        $claims = AviationParametricClaim::where('disruption_case_id', $sagaResult['disruption_case_id'])->get();
        $this->assertNotEmpty($claims);
        $this->assertEquals('VOLCANIC_ERUPTION_CLOSURE', $claims->first()->trigger_type);
        $this->assertEquals(100000000, $claims->first()->payout_amount_minor_unit); // Rp1.000.000 santunan tunai
    }
}
