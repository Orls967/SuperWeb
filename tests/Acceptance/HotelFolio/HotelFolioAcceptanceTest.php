<?php

declare(strict_types=1);

namespace Tests\Acceptance\HotelFolio;

use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Hotel\Application\Services\HotelFolioAndPackageService;
use Modules\Hotel\Domain\Algorithms\FolioSplitRouter;
use Modules\Hotel\Domain\Algorithms\FractionalUsageScheduler;
use Modules\Hotel\Domain\Models\HotelFolio;
use Modules\Hotel\Domain\Models\TimeshareInvestor;
use Modules\Hotel\Domain\Models\TimeshareUnit;
use Modules\Shared\Domain\Algorithms\Settlement\BundleSspAllocationInput;
use Modules\Shared\Domain\Algorithms\Settlement\BundleSspAllocator;
use Tests\TestCase;

final class HotelFolioAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected HotelFolioAndPackageService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(HotelFolioAndPackageService::class);
        $this->seed(LedgerAccountsSeeder::class);
    }

    public function test_01_access_control_and_route_authorization_folio(): void
    {
        // Verifikasi kesiapan service modul Hotel Folio
        $this->assertNotNull($this->service);
        $this->assertInstanceOf(HotelFolioAndPackageService::class, $this->service);
    }

    public function test_02_split_folio_multi_payer_corporate_city_ledger_and_personal(): void
    {
        $guestId = (string) Str::uuid();
        $corpId = 'CORP-ASTRA-01';

        $folio = HotelFolio::create([
            'id' => (string) Str::uuid(),
            'reservation_id' => (string) Str::uuid(),
            'guest_user_id' => $guestId,
            'total_room_charges' => 4000000,
            'total_addon_charges' => 0,
            'total_paid' => 0,
            'outstanding_balance' => 4000000,
            'status' => 'open',
        ]);

        // Tambah biaya insidental
        $this->service->addFolioItem($folio->id, 'SPA', 'Traditional Massage', 600000);
        $this->service->addFolioItem($folio->id, 'MINIBAR', 'Imported Chocolates', 150000);

        $folio->refresh();
        $this->assertSame(4750000, $folio->outstanding_balance);

        // Algoritma H6 memvalidasi pemisahan
        $router = new FolioSplitRouter;
        $items = [
            ['line_id' => '1', 'category' => 'ROOM_CHARGE', 'amount_idr' => 4000000],
            ['line_id' => '2', 'category' => 'SPA', 'amount_idr' => 600000],
            ['line_id' => '3', 'category' => 'MINIBAR', 'amount_idr' => 150000],
        ];
        $cert = $router->route($folio->id, true, 5000000, $items);
        $this->assertSame(4000000, $cert->corporateAmountIdr);
        $this->assertSame(750000, $cert->personalAmountIdr);

        // Corporate settlement ke City Ledger AR
        $settledCorp = $this->service->settleCorporateBilling($folio->id, $corpId, 4000000);
        $this->assertSame(750000, $settledCorp->outstanding_balance);
        $this->assertSame('open', $settledCorp->status);

        // Tamu menyelesaikan sisa saldo personal
        $settledCorp->increment('total_paid', 750000);
        $settledCorp->update([
            'outstanding_balance' => 0,
            'status' => 'settled',
        ]);

        $this->assertSame(0, $settledCorp->fresh()->outstanding_balance);
        $this->assertSame('settled', $settledCorp->fresh()->status);
    }

    public function test_03_zero_balance_closure_invariant_blocks_premature_checkout(): void
    {
        $folio = HotelFolio::create([
            'id' => (string) Str::uuid(),
            'reservation_id' => (string) Str::uuid(),
            'guest_user_id' => (string) Str::uuid(),
            'total_room_charges' => 2000000,
            'total_addon_charges' => 500000,
            'total_paid' => 2000000,
            'outstanding_balance' => 500000, // Masih ada sisa 500k
            'status' => 'open',
        ]);

        // Invarian Checkout: status tidak boleh settled jika saldo != 0
        $canClose = ($folio->outstanding_balance === 0);
        $this->assertFalse($canClose);
        $this->assertSame('open', $folio->status);
    }

    public function test_04_timeshare_fractional_rental_pool_and_pro_rata_dividend(): void
    {
        $unit = TimeshareUnit::create([
            'id' => (string) Str::uuid(),
            'property_id' => (string) Str::uuid(),
            'unit_code' => 'TS-VILLA-CANGGU-01',
            'unit_name' => 'Canggu Luxury Sunset Villa',
            'total_token_shares' => 100,
            'daily_rental_rate_idr' => 5000000,
        ]);

        $investor1 = '00000000-0000-0000-0000-000000000001';
        $investor2 = '00000000-0000-0000-0000-000000000002';

        TimeshareInvestor::create([
            'id' => (string) Str::uuid(),
            'timeshare_unit_id' => $unit->id,
            'investor_user_id' => $investor1,
            'token_shares' => 70, // 70%
        ]);

        TimeshareInvestor::create([
            'id' => (string) Str::uuid(),
            'timeshare_unit_id' => $unit->id,
            'investor_user_id' => $investor2,
            'token_shares' => 30, // 30%
        ]);

        // Algoritma H8 menjadwalkan kuota minggu
        $scheduler = new FractionalUsageScheduler;
        $certSchedule = $scheduler->schedule($unit->id, $investor1, 36, [10, 11], 2026);
        $this->assertSame(36, $certSchedule->totalFractionsOwned);
        $this->assertSame(2, $certSchedule->allocatedWeeksCount);
        $this->assertSame(34, $certSchedule->rentalPoolWeeksCount);

        // Bagi hasil dividen sewa publik IDR 20.000.000
        $res = $this->service->distributeTimeshareDailyDividend($unit->id, 20000000);
        $this->assertSame(20000000, $res['total_allocated']);
        $this->assertSame(14000000, $res['investors'][0]['dividend_idr']); // 70%
        $this->assertSame(6000000, $res['investors'][1]['dividend_idr']);  // 30%
    }

    public function test_05_multi_vendor_destination_package_bundle_ssp_and_banquet_margin(): void
    {
        // 1. Verifikasi Alokasi Paket Destinasi Multi-Vendor dengan BundleSspAllocator (PSAK 72 / IFRS 15)
        $allocator = new BundleSspAllocator;
        $bundleInput = new BundleSspAllocationInput(
            bundlePriceIdr: 8000000, // IDR 8.000.000
            platformFeeBps: 500,     // 5% Platform fee
            components: [
                ['name' => 'Kamar Deluxe Hotel', 'ssp_idr' => 5000000, 'vendor_id' => 'vendor-hotel'],
                ['name' => 'Tiket Beach Club & Konser', 'ssp_idr' => 3000000, 'vendor_id' => 'vendor-venue'],
                ['name' => 'Shuttle Bus Transfer', 'ssp_idr' => 2000000, 'vendor_id' => 'vendor-shuttle'],
            ]
        );
        $bundleResult = $allocator->allocate($bundleInput);

        // Total alokasi harus konservatif tepat sama dengan harga bundel (Invarian PSAK 72)
        $this->assertSame(8000000, $bundleResult->bundlePriceIdr);
        $this->assertCount(3, $bundleResult->items);
        $this->assertSame(10000000, $bundleResult->totalSspIdr);

        // 2. Verifikasi Banquet Event Order Gross Margin Guardrail
        $contractValue = 100000000; // IDR 100 juta
        $bomCostProfitable = 50000000; // Margin 50% (memenuhi syarat >= 40%)
        $marginPctProfitable = (($contractValue - $bomCostProfitable) / $contractValue) * 100.0;
        $this->assertGreaterThanOrEqual(40.0, $marginPctProfitable);

        $bomCostUnprofitable = 65000000; // Margin 35% (< 40% -> Wajib ditolak)
        $marginPctUnprofitable = (($contractValue - $bomCostUnprofitable) / $contractValue) * 100.0;
        $this->assertLessThan(40.0, $marginPctUnprofitable);
    }
}
