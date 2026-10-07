<?php

declare(strict_types=1);

namespace Modules\Core\tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Core\Application\Services\TwelveLinesCrossEcosystemService;
use Modules\Hotel\Domain\Models\HotelFolio;
use Tests\TestCase;

class CrossEcosystemTwelveLinesTest extends TestCase
{
    use RefreshDatabase;

    protected TwelveLinesCrossEcosystemService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(TwelveLinesCrossEcosystemService::class);

        $accounts = [
            'escrow:medical_tourism:IDR' => 'escrow',
            'hsp:billing_clearing:IDR' => 'revenue',
            'htl:settlement_clearing:IDR' => 'revenue',
            'lgx:freight_revenue' => 'revenue',
            'htl:city_ledger_receivable:IDR' => 'asset',
            'ven:pos_revenue:IDR' => 'revenue',
        ];

        foreach ($accounts as $code => $kind) {
            LedgerAccount::firstOrCreate(
                ['code' => $code],
                [
                    'name' => "Account {$code}",
                    'kind' => $kind,
                    'asset_code' => 'IDR',
                    'allow_negative' => true,
                    'cached_balance' => '0',
                ]
            );
        }
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

    public function test_venue_charge_posted_to_hotel_folio(): void
    {
        $folio = HotelFolio::create([
            'id' => (string) Str::uuid(),
            'reservation_id' => (string) Str::uuid(),
            'guest_user_id' => (string) Str::uuid(),
            'total_room_charges' => 2000000,
            'total_addon_charges' => 0,
            'total_paid' => 0,
            'outstanding_balance' => 2000000,
            'status' => 'open',
        ]);

        $updated = $this->service->chargeVenueConsumptionToHotelFolio(
            hotelFolioId: $folio->id,
            venueTicketNumber: 'TKT-ATLAS-VIP-88',
            chargeDescription: 'Beach Club VIP Cabana Champagne Bottle',
            amountIdr: 1500000
        );

        $this->assertEquals(1500000, $updated->total_addon_charges);
        $this->assertEquals(3500000, $updated->outstanding_balance);
    }
}
