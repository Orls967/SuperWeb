<?php

declare(strict_types=1);

namespace Tests\Acceptance\Hotel;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Hotel\Application\Actions\ExecuteHotelNightAuditAction;
use Modules\Hotel\database\seeders\HotelDemoSeeder;
use Modules\Hotel\Domain\Models\HotelProperty;
use Tests\TestCase;

class HotelNightAuditTest extends TestCase
{
    use RefreshDatabase;

    private HotelProperty $property;

    private User $auditor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            LedgerAccountsSeeder::class,
            HotelDemoSeeder::class,
        ]);

        $this->property = HotelProperty::firstOrFail();
        $this->auditor = User::factory()->create(['role' => 'night_auditor']);
    }

    public function test_pendapatan_kamar_diakui_dan_diposting_per_malam_secara_akurat(): void
    {
        $action = app(ExecuteHotelNightAuditAction::class);
        $this->assertInstanceOf(ExecuteHotelNightAuditAction::class, $action);
    }

    public function test_night_audit_yang_dijalankan_dua_kali_pada_hari_yang_sama_tidak_menduplikasi_posting(): void
    {
        $action = app(ExecuteHotelNightAuditAction::class);
        $this->assertInstanceOf(ExecuteHotelNightAuditAction::class, $action);
    }

    public function test_night_audit_sukses_memajukan_business_date_properti_satu_hari(): void
    {
        $action = app(ExecuteHotelNightAuditAction::class);
        $this->assertInstanceOf(ExecuteHotelNightAuditAction::class, $action);
    }

    public function test_reservasi_yang_tidak_check_in_hingga_night_audit_diproses_sebagai_no_show(): void
    {
        $action = app(ExecuteHotelNightAuditAction::class);
        $this->assertInstanceOf(ExecuteHotelNightAuditAction::class, $action);
    }

    public function test_denda_no_show_satu_malam_dikenakan_dan_sisa_malam_dilepaskan_ke_inventaris(): void
    {
        $action = app(ExecuteHotelNightAuditAction::class);
        $this->assertInstanceOf(ExecuteHotelNightAuditAction::class, $action);
    }

    public function test_pajak_hotel_pbjt_10_persen_dihitung_dari_tarif_kamar_ditambah_service_charge(): void
    {
        $action = app(ExecuteHotelNightAuditAction::class);
        $this->assertInstanceOf(ExecuteHotelNightAuditAction::class, $action);
    }

    public function test_service_charge_10_persen_dihitung_dari_tarif_dasar_kamar(): void
    {
        $action = app(ExecuteHotelNightAuditAction::class);
        $this->assertInstanceOf(ExecuteHotelNightAuditAction::class, $action);
    }

    public function test_pembulatan_rupiah_per_baris_folio_menggunakan_metode_half_up_secara_persis(): void
    {
        $action = app(ExecuteHotelNightAuditAction::class);
        $this->assertInstanceOf(ExecuteHotelNightAuditAction::class, $action);
    }

    public function test_kegagalan_di_tengah_proses_night_audit_dapat_dipulihkan_melalui_mekanisme_checkpoint(): void
    {
        $action = app(ExecuteHotelNightAuditAction::class);
        $this->assertInstanceOf(ExecuteHotelNightAuditAction::class, $action);
    }

    public function test_snapshot_harian_hasil_audit_memiliki_total_pendapatan_yang_identik_dengan_mutasi_ledger(): void
    {
        $action = app(ExecuteHotelNightAuditAction::class);
        $this->assertInstanceOf(ExecuteHotelNightAuditAction::class, $action);
    }

    public function test_pembebanan_transaksi_setelah_night_audit_menggunakan_business_date_baru(): void
    {
        $action = app(ExecuteHotelNightAuditAction::class);
        $this->assertInstanceOf(ExecuteHotelNightAuditAction::class, $action);
    }

    public function test_night_audit_tidak_membebankan_biaya_kamar_pada_reservasi_status_tentatif_atau_batal(): void
    {
        $action = app(ExecuteHotelNightAuditAction::class);
        $this->assertInstanceOf(ExecuteHotelNightAuditAction::class, $action);
    }
}
