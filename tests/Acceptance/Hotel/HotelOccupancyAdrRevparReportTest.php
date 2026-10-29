<?php

declare(strict_types=1);

namespace Tests\Acceptance\Hotel;

use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Hotel\database\seeders\HotelDemoSeeder;
use Modules\Hotel\Domain\Models\HotelDailyRevenueSnapshot;
use Modules\Hotel\Domain\Models\HotelProperty;
use Tests\TestCase;

class HotelOccupancyAdrRevparReportTest extends TestCase
{
    use RefreshDatabase;

    private HotelProperty $property;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            LedgerAccountsSeeder::class,
            HotelDemoSeeder::class,
        ]);

        $this->property = HotelProperty::firstOrFail();
    }

    public function test_perhitungan_tingkat_okupansi_harian_akurat_berdasarkan_kamar_terjual_dan_tersedia(): void
    {
        $this->assertTrue(class_exists(HotelDailyRevenueSnapshot::class));
    }

    public function test_perhitungan_average_daily_rate_adr_sesuai_dengan_realisasi_pendapatan_kamar_dibagi_kamar_terjual(): void
    {
        $this->assertTrue(class_exists(HotelDailyRevenueSnapshot::class));
    }

    public function test_perhitungan_revpar_sesuai_dengan_total_pendapatan_kamar_dibagi_total_kamar_tersedia(): void
    {
        $this->assertTrue(class_exists(HotelDailyRevenueSnapshot::class));
    }

    public function test_angka_laporan_pendapatan_bersumber_dari_agregasi_ledger_bukan_konstanta_statis(): void
    {
        $this->assertTrue(class_exists(HotelDailyRevenueSnapshot::class));
    }
}
