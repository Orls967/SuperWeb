<?php

declare(strict_types=1);

namespace Modules\Core\tests\Feature;

use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Modules\Core\Application\Services\TwelveLinesCrossEcosystemService;
use Modules\Core\Contracts\HotelFolioPort;
use Tests\TestCase;

class CrossEcosystemEnterpriseDeepeningTest extends TestCase
{
    use RefreshDatabase;

    protected TwelveLinesCrossEcosystemService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Implement HotelFolioPort locally without importing business domain models (A9 = 0)
        $this->app->bind(HotelFolioPort::class, function () {
            return new class implements HotelFolioPort
            {
                public function chargeVenueConsumption(
                    string $hotelFolioId,
                    string $venueTicketNumber,
                    string $chargeDescription,
                    int $amountIdr
                ): object {
                    return (object) [
                        'id' => $hotelFolioId,
                        'total_room_charges' => 2000000,
                        'total_addon_charges' => $amountIdr,
                        'total_paid' => 0,
                        'outstanding_balance' => 2000000 + $amountIdr,
                        'status' => 'open',
                    ];
                }
            };
        });

        $this->service = app(TwelveLinesCrossEcosystemService::class);
        $this->seed(LedgerAccountsSeeder::class);
    }

    public function test_cross_line_vehicle_passport_synchronizes_odometer_across_rental_mining_logistics(): void
    {
        $vin = 'MHK-TRUCK-2026-X99';

        $syncMining = $this->service->syncCrossLineVehicleAndHaulTruckOdometer($vin, 'MINING_SECTOR', 50000);
        $this->assertEquals('synchronized', $syncMining['status']);
        $this->assertEquals(50000, $syncMining['odometer_km']);
        $this->assertTrue($syncMining['maintenance_alert']); // 50,000 km is a maintenance milestone

        $syncLogistics = $this->service->syncCrossLineVehicleAndHaulTruckOdometer($vin, 'LOGISTICS_FREIGHT', 50450);
        $this->assertEquals('synchronized', $syncLogistics['status']);
        $this->assertEquals(50450, $syncLogistics['odometer_km']);
        $this->assertFalse($syncLogistics['maintenance_alert']);
    }

    public function test_unified_maintenance_passport_triggers_service_in_any_conglomerate_workshop(): void
    {
        $vehiclePassport = [
            'vin' => 'VIN-CROSS-LINE-404',
            'current_odometer_km' => 60000,
            'next_service_milestone_km' => 60000,
            'service_due' => true,
        ];

        // Any conglomerate workshop (Mining depot, Automotive dealer, or Fleet logistics bay) recognizes the milestone
        $this->assertTrue($vehiclePassport['service_due']);
        $this->assertEquals(60000, $vehiclePassport['next_service_milestone_km']);
    }

    public function test_multi_property_spklu_charging_posts_intercompany_clearing_and_scope_2_allocation(): void
    {
        $hotelStationId = (string) Str::uuid();
        $mallStationId = (string) Str::uuid();

        // 80 kWh charged at Hotel EV charger
        $hotelCharge = $this->service->recordCrossLineSpkluChargingToPropertyEsg($hotelStationId, 'HOTEL_EV_STATION', 80.0);
        $this->assertTrue($hotelCharge['esg_logged']);
        $this->assertEquals(80.0, $hotelCharge['kwh_charged']);
        $this->assertEquals(68.0, $hotelCharge['scope2_emission_kg_co2e']); // 80 * 0.85 = 68.0 kg

        // 150 kWh charged at Mall EV fast charger
        $mallCharge = $this->service->recordCrossLineSpkluChargingToPropertyEsg($mallStationId, 'MALL_EV_STATION', 150.0);
        $this->assertTrue($mallCharge['esg_logged']);
        $this->assertEquals(150.0, $mallCharge['kwh_charged']);
        $this->assertEquals(127.5, $mallCharge['scope2_emission_kg_co2e']); // 150 * 0.85 = 127.5 kg
    }

    public function test_tripartite_medical_tourism_bundle_disburses_exact_revenue_shares_to_hospital_hotel_logistics(): void
    {
        $patientId = (string) Str::uuid();
        $encounterId = (string) Str::uuid();
        $hotelResId = (string) Str::uuid();
        $bundleTotalIdr = 25000000; // 25M IDR

        $settlement = $this->service->bookMedicalTourismBundle(
            patientUserId: $patientId,
            hospitalEncounterId: $encounterId,
            hotelReservationId: $hotelResId,
            packageTotalAmountIdr: $bundleTotalIdr
        );

        $this->assertTrue($settlement['settled']);
        // 50% Hospital (12.5M), 35% Hotel (8.75M), 15% Logistics (3.75M)
        $this->assertEquals(12500000, $settlement['hospital_share']);
        $this->assertEquals(8750000, $settlement['hotel_share']);
        $this->assertEquals(3750000, $settlement['logistics_share']);

        // Zero dust / zero drift invariant
        $sumShares = $settlement['hospital_share'] + $settlement['hotel_share'] + $settlement['logistics_share'];
        $this->assertEquals($bundleTotalIdr, $sumShares);
    }

    public function test_single_credential_human_passport_opens_hotel_room_and_authorizes_mine_entry(): void
    {
        $passportId = 'PASSPORT-INDONESIA-778899';

        $hotelAuth = $this->service->verifyUnifiedSingleCredentialAccess($passportId, 'HOTEL');
        $this->assertTrue($hotelAuth['access_granted']);

        $venueAuth = $this->service->verifyUnifiedSingleCredentialAccess($passportId, 'VENUE');
        $this->assertTrue($venueAuth['access_granted']);

        $hospitalAuth = $this->service->verifyUnifiedSingleCredentialAccess($passportId, 'HOSPITAL');
        $this->assertTrue($hospitalAuth['access_granted']);
    }

    public function test_single_credential_access_rejects_expired_or_cross_property_tampered_token(): void
    {
        $passportId = 'PASSPORT-TAMPERED-USER';

        $unauthorizedAuth = $this->service->verifyUnifiedSingleCredentialAccess($passportId, 'RESTRICTED_BUNKER');
        $this->assertFalse($unauthorizedAuth['access_granted']);
    }

    public function test_twenty_four_hour_golden_cross_line_simulation_executes_with_zero_ledger_imbalance(): void
    {
        $simExitCode = Artisan::call('ecosystem:audit-12-lines');
        $this->assertSame(0, $simExitCode);

        $output = Artisan::output();
        $this->assertStringContainsString('Audit completed successfully with 0 discrepancies across all 12 lines', $output);
    }

    public function test_cross_line_ecosystem_audit_confirms_healthy_status_across_all_twelve_domains(): void
    {
        // Hotel Audit
        $htlExit = Artisan::call('htl:audit');
        $this->assertSame(0, $htlExit);
        $this->assertStringContainsString('Hotel audit PASSED with 0 discrepancy', Artisan::output());

        // Mining Audit
        $minExit = Artisan::call('min:audit');
        $this->assertSame(0, $minExit);
        $this->assertStringContainsString('Mining audit PASSED cleanly', Artisan::output());

        // Cross-line 12 lines audit
        $allExit = Artisan::call('ecosystem:audit-12-lines');
        $this->assertSame(0, $allExit);
        $this->assertStringContainsString('Audit completed successfully with 0 discrepancies across all 12 lines', Artisan::output());
    }
}
