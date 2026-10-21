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

class CrossEcosystemTwelveLinesTest extends TestCase
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

    public function test_medical_tourism_bundle_settlement_cross_3_lines(): void
    {
        $patientId = (string) Str::uuid();
        $encounterId = (string) Str::uuid();
        $resId = (string) Str::uuid();

        // 10,000,000 IDR package (50% RS, 35% Hotel, 15% Logistik)
        $result = $this->service->bookMedicalTourismBundle(
            patientUserId: $patientId,
            hospitalEncounterId: $encounterId,
            hotelReservationId: $resId,
            packageTotalAmountIdr: 10000000
        );

        $this->assertTrue($result['settled']);
        $this->assertEquals(5000000, $result['hospital_share']);
        $this->assertEquals(3500000, $result['hotel_share']);
        $this->assertEquals(1500000, $result['logistics_share']);
        $this->assertEquals(10000000, $result['hospital_share'] + $result['hotel_share'] + $result['logistics_share']);
    }

    public function test_venue_charge_posted_to_hotel_folio_via_port(): void
    {
        $folioId = (string) Str::uuid();

        $updated = $this->service->chargeVenueConsumptionToHotelFolio(
            hotelFolioId: $folioId,
            venueTicketNumber: 'TKT-ATLAS-VIP-88',
            chargeDescription: 'Beach Club VIP Cabana Champagne Bottle',
            amountIdr: 1500000
        );

        $this->assertEquals(1500000, $updated->total_addon_charges);
        $this->assertEquals(3500000, $updated->outstanding_balance);
        $this->assertEquals('open', $updated->status);
    }

    public function test_odometer_sync_across_rental_and_mining_fleet(): void
    {
        $vehicleId = (string) Str::uuid();

        $sync = $this->service->syncCrossLineVehicleAndHaulTruckOdometer(
            vehicleId: $vehicleId,
            lineSource: 'MINING_HAUL_TRUCK',
            odometerKm: 30000
        );

        $this->assertEquals('synchronized', $sync['status']);
        $this->assertEquals(30000, $sync['odometer_km']);
        $this->assertTrue($sync['maintenance_alert']); // 30,000 km triggers 10,000 km maintenance milestone
    }

    public function test_spklu_ev_charging_across_properties_with_esg_scope2(): void
    {
        $propertyId = (string) Str::uuid();

        $esg = $this->service->recordCrossLineSpkluChargingToPropertyEsg(
            propertyId: $propertyId,
            propertyType: 'MALL_EV_STATION',
            kwhCharged: 120.5
        );

        $this->assertTrue($esg['esg_logged']);
        $this->assertEquals(120.5, $esg['kwh_charged']);
        // 120.5 * 0.85 = 102.425 kg CO2e
        $this->assertEquals(102.425, $esg['scope2_emission_kg_co2e']);
    }

    public function test_unified_single_credential_access_control(): void
    {
        $passportId = (string) Str::uuid();

        // Valid access to Hotel, Venue, Hospital
        $hotelAccess = $this->service->verifyUnifiedSingleCredentialAccess($passportId, 'HOTEL');
        $this->assertTrue($hotelAccess['access_granted']);

        $venueAccess = $this->service->verifyUnifiedSingleCredentialAccess($passportId, 'VENUE');
        $this->assertTrue($venueAccess['access_granted']);

        $hspAccess = $this->service->verifyUnifiedSingleCredentialAccess($passportId, 'HOSPITAL');
        $this->assertTrue($hspAccess['access_granted']);

        // Invalid domain rejected
        $unknownAccess = $this->service->verifyUnifiedSingleCredentialAccess($passportId, 'UNKNOWN_SECTOR');
        $this->assertFalse($unknownAccess['access_granted']);
    }

    public function test_end_to_end_cross_line_ecosystem_audit_zero_discrepancy(): void
    {
        $exit = Artisan::call('ecosystem:audit-12-lines');
        $this->assertSame(0, $exit);
        $this->assertStringContainsString('Audit completed successfully with 0 discrepancies across all 12 lines', Artisan::output());
    }
}
