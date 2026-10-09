<?php

declare(strict_types=1);

namespace Modules\Hotel\tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Banking\Domain\Models\LedgerAccount;
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

        $accounts = [
            'expense:htl_timeshare_payout:IDR' => 'expense',
            'escrow:destination_package:IDR' => 'escrow',
            'vendor:hotel:IDR' => 'liability',
            'vendor:festival:IDR' => 'liability',
            'vendor:transport:IDR' => 'liability',
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

        $investor1 = (string) Str::uuid();
        $investor2 = (string) Str::uuid();

        foreach ([$investor1, $investor2] as $invId) {
            LedgerAccount::firstOrCreate(
                ['code' => "investor:dividend:{$invId}:IDR"],
                [
                    'name' => "Investor Dividend {$invId}",
                    'kind' => 'liability',
                    'asset_code' => 'IDR',
                    'allow_negative' => true,
                    'cached_balance' => '0',
                ]
            );
        }

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
}
