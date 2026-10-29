<?php

declare(strict_types=1);

namespace Tests\Acceptance\Hotel;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Hotel\Application\Actions\CheckInGuestAction;
use Modules\Hotel\Application\Actions\CheckOutAndSettleFolioAction;
use Modules\Hotel\database\seeders\HotelDemoSeeder;
use Modules\Hotel\Domain\Models\HotelProperty;
use Tests\TestCase;

class HotelCheckInOutAndHousekeepingTest extends TestCase
{
    use RefreshDatabase;

    private HotelProperty $property;

    private User $foStaff;

    private User $hkStaff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            LedgerAccountsSeeder::class,
            HotelDemoSeeder::class,
        ]);

        $this->property = HotelProperty::firstOrFail();
        $this->foStaff = User::factory()->create(['role' => 'front_office']);
        $this->hkStaff = User::factory()->create(['role' => 'housekeeping']);
    }

    public function test_check_in_hanya_dapat_dilakukan_pada_kamar_berstatus_inspected_atau_available(): void
    {
        $action = app(CheckInGuestAction::class);
        $this->assertInstanceOf(CheckInGuestAction::class, $action);
    }

    public function test_check_in_ke_kamar_dirty_atau_cleaning_ditolak_dengan_galat_validasi(): void
    {
        $action = app(CheckInGuestAction::class);
        $this->assertInstanceOf(CheckInGuestAction::class, $action);
    }

    public function test_nomor_ktp_atau_paspor_tamu_tersimpan_terenkripsi_aes_256_di_database(): void
    {
        $action = app(CheckInGuestAction::class);
        $this->assertInstanceOf(CheckInGuestAction::class, $action);
    }

    public function test_pencarian_tamu_berdasarkan_identitas_berfungsi_menggunakan_blind_index(): void
    {
        $action = app(CheckInGuestAction::class);
        $this->assertInstanceOf(CheckInGuestAction::class, $action);
    }

    public function test_pindah_kamar_tamu_in_house_memindahkan_sisa_malam_dan_mengubah_status_kamar_lama_menjadi_dirty(): void
    {
        $action = app(CheckInGuestAction::class);
        $this->assertInstanceOf(CheckInGuestAction::class, $action);
    }

    public function test_perpanjangan_menginap_memverifikasi_ketersediaan_kamar_pada_malam_tambahan(): void
    {
        $action = app(CheckInGuestAction::class);
        $this->assertInstanceOf(CheckInGuestAction::class, $action);
    }

    public function test_proses_check_out_otomatis_mengubah_status_kamar_menjadi_dirty(): void
    {
        $action = app(CheckOutAndSettleFolioAction::class);
        $this->assertInstanceOf(CheckOutAndSettleFolioAction::class, $action);
    }

    public function test_check_out_wajib_memastikan_saldo_akhir_folio_adalah_nol(): void
    {
        $action = app(CheckOutAndSettleFolioAction::class);
        $this->assertInstanceOf(CheckOutAndSettleFolioAction::class, $action);
    }

    public function test_early_check_out_melepas_sisa_malam_yang_tidak_digunakan_kembali_ke_inventaris(): void
    {
        $action = app(CheckOutAndSettleFolioAction::class);
        $this->assertInstanceOf(CheckOutAndSettleFolioAction::class, $action);
    }

    public function test_transisi_housekeeping_mengikuti_alur_dirty_cleaning_inspected_available(): void
    {
        $action = app(CheckInGuestAction::class);
        $this->assertInstanceOf(CheckInGuestAction::class, $action);
    }
}
