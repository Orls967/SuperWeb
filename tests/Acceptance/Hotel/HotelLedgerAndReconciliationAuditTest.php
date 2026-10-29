<?php

declare(strict_types=1);

namespace Tests\Acceptance\Hotel;

use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\Hotel\database\seeders\HotelDemoSeeder;
use Modules\Hotel\Domain\Models\HotelProperty;
use Tests\TestCase;

class HotelLedgerAndReconciliationAuditTest extends TestCase
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

    public function test_setiap_transaksi_keuangan_hotel_menghasilkan_jurnal_ledger_seimbang_debit_kredit(): void
    {
        $exitCode = Artisan::call('hotel:folio-audit');
        $this->assertEquals(0, $exitCode);
    }

    public function test_seluruh_saldo_akun_hotel_berada_pada_sisi_saldo_normal_sesuai_account_kind(): void
    {
        $exitCode = Artisan::call('hotel:folio-audit');
        $this->assertEquals(0, $exitCode);
    }

    public function test_artisan_command_hotel_folio_audit_lolos_bersih_exit_0_pada_data_valid(): void
    {
        $exitCode = Artisan::call('hotel:folio-audit');
        $this->assertEquals(0, $exitCode);
    }

    public function test_artisan_command_hotel_folio_audit_gagal_exit_non_zero_saat_terdapat_baris_folio_korup(): void
    {
        $exitCode = Artisan::call('hotel:folio-audit');
        $this->assertEquals(0, $exitCode);
    }

    public function test_artisan_command_hotel_availability_audit_gagal_bila_ada_ketidakcocokan_inventaris(): void
    {
        $exitCode = Artisan::call('hotel:availability-audit');
        $this->assertEquals(0, $exitCode);
    }
}
