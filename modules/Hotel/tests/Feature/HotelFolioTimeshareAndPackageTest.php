<?php

declare(strict_types=1);

namespace Modules\Hotel\tests\Feature;

use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Hotel\Application\Services\HotelFolioAndPackageService;
use Modules\Hotel\Domain\Models\DestinationPackage;
use Modules\Hotel\Domain\Models\HotelFolio;
use Modules\Hotel\Domain\Models\TimeshareInvestor;
use Modules\Hotel\Domain\Models\TimeshareUnit;
use Tests\TestCase;

class HotelFolioTimeshareAndPackageTest extends TestCase
{
    use RefreshDatabase;

    protected HotelFolioAndPackageService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(HotelFolioAndPackageService::class);

        $this->seed(LedgerAccountsSeeder::class);
    }

    public function test_folio_addon_charge_roll_up(): void
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

        // Add Room service 250k
        $this->service->addFolioItem($folio->id, 'ROOM_SERVICE', 'Nasi Goreng Wagyu & Fresh Coconut', 250000);
        // Add Spa 500k
        $this->service->addFolioItem($folio->id, 'SPA', 'Balinese Massage 90 Min', 500000);

        $updatedFolio = $folio->fresh();
        $this->assertEquals(750000, $updatedFolio->total_addon_charges);
        $this->assertEquals(2750000, $updatedFolio->outstanding_balance);
    }

    public function test_timeshare_fractional_dividend_distribution_invariants(): void
    {
        $unit = TimeshareUnit::create([
            'id' => (string) Str::uuid(),
            'property_id' => (string) Str::uuid(),
            'unit_code' => 'TS-VILLA-BALI-01',
            'unit_name' => 'Royal Beachfront Villa',
            'total_token_shares' => 100,
            'daily_rental_rate_idr' => 10000000,
        ]);

        $investor1 = '00000000-0000-0000-0000-000000000001';
        $investor2 = '00000000-0000-0000-0000-000000000002';

        TimeshareInvestor::create([
            'id' => (string) Str::uuid(),
            'timeshare_unit_id' => $unit->id,
            'investor_user_id' => $investor1,
            'token_shares' => 60, // 60%
        ]);

        TimeshareInvestor::create([
            'id' => (string) Str::uuid(),
            'timeshare_unit_id' => $unit->id,
            'investor_user_id' => $investor2,
            'token_shares' => 40, // 40%
        ]);

        // Distribute net income of 10,000,000 IDR
        $res = $this->service->distributeTimeshareDailyDividend($unit->id, 10000000);

        $this->assertEquals(10000000, $res['total_allocated']);
        $this->assertEquals(6000000, $res['investors'][0]['dividend_idr']);
        $this->assertEquals(4000000, $res['investors'][1]['dividend_idr']);
    }

    public function test_destination_package_multi_vendor_settlement(): void
    {
        $package = DestinationPackage::create([
            'id' => (string) Str::uuid(),
            'package_code' => 'PKG-BALI-EXPERIENCE',
            'title' => 'Bali Sunset & Music Package',
            'total_price_idr' => 6000000,
            'vendor_shares' => [
                ['vendor' => 'HOTEL', 'amount' => 3000000, 'account' => 'vendor:hotel:IDR'],
                ['vendor' => 'FESTIVAL', 'amount' => 2000000, 'account' => 'vendor:festival:IDR'],
                ['vendor' => 'TRANSPORT', 'amount' => 1000000, 'account' => 'vendor:transport:IDR'],
            ],
        ]);

        $res = $this->service->settleDestinationPackage($package->id, (string) Str::uuid());

        $this->assertEquals(6000000, $res['total_paid']);
        $this->assertEquals(3, $res['settled_vendors']);
    }

    public function test_corporate_billing_posts_to_city_ledger_ar(): void
    {
        $folio = HotelFolio::create([
            'id' => (string) Str::uuid(),
            'reservation_id' => (string) Str::uuid(),
            'guest_user_id' => (string) Str::uuid(),
            'total_room_charges' => 5000000,
            'total_addon_charges' => 1500000,
            'total_paid' => 0,
            'outstanding_balance' => 6500000,
            'status' => 'open',
        ]);

        $settledFolio = $this->service->settleCorporateBilling($folio->id, 'CORP-ASTRA-01', 6500000);

        $this->assertEquals(0, $settledFolio->outstanding_balance);
        $this->assertEquals('settled', $settledFolio->status);

        $this->assertDatabaseHas('bank_ledger_transactions', [
            'type' => 'HOTEL_FOLIO_SETTLEMENT',
            'reference_type' => 'htl_folios',
            'reference_id' => $folio->id,
        ]);
    }

    public function test_hotel_audit_zero_discrepancy(): void
    {
        $exitHotelAudit = \Illuminate\Support\Facades\Artisan::call('hotel:audit');
        $this->assertSame(0, $exitHotelAudit);
        $this->assertStringContainsString('Hotel audit PASSED with 0 discrepancy', \Illuminate\Support\Facades\Artisan::output());
    }
}
