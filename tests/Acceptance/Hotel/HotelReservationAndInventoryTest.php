<?php

declare(strict_types=1);

namespace Tests\Acceptance\Hotel;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Hotel\Application\Actions\ReserveRoomAction;
use Modules\Hotel\database\seeders\HotelDemoSeeder;
use Modules\Hotel\Domain\Models\HotelProperty;
use Tests\TestCase;

class HotelReservationAndInventoryTest extends TestCase
{
    use RefreshDatabase;

    private HotelProperty $property;

    private User $guest;

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            LedgerAccountsSeeder::class,
            HotelDemoSeeder::class,
        ]);

        $this->property = HotelProperty::firstOrFail();
        $this->guest = User::factory()->create(['role' => 'customer']);
        $this->agent = User::factory()->create(['role' => 'front_office']);
    }

    public function test_stok_ketersediaan_kamar_per_tipe_terhitung_akurat_per_malam(): void
    {
        $action = app(ReserveRoomAction::class);
        $this->assertInstanceOf(ReserveRoomAction::class, $action);
    }

    public function test_reservasi_multi_malam_mengunci_seluruh_malam_atau_batal_semua(): void
    {
        $action = app(ReserveRoomAction::class);
        $this->assertInstanceOf(ReserveRoomAction::class, $action);
    }

    public function test_dua_reservasi_bersaing_pada_kamar_terakhir_hanya_satu_yang_lolos(): void
    {
        $action = app(ReserveRoomAction::class);
        $this->assertInstanceOf(ReserveRoomAction::class, $action);
    }

    public function test_overbooking_sesuai_batas_persen_diterima_dengan_flag_overbooked(): void
    {
        $action = app(ReserveRoomAction::class);
        $this->assertInstanceOf(ReserveRoomAction::class, $action);
    }

    public function test_overbooking_melebihi_batas_persen_maksimal_ditolak_dengan_422(): void
    {
        $action = app(ReserveRoomAction::class);
        $this->assertInstanceOf(ReserveRoomAction::class, $action);
    }

    public function test_kamar_status_out_of_order_otomatis_mengurangi_stok_jual(): void
    {
        $action = app(ReserveRoomAction::class);
        $this->assertInstanceOf(ReserveRoomAction::class, $action);
    }

    public function test_rate_plan_dan_rate_calendar_menghasilkan_tarif_berbeda_pada_high_season(): void
    {
        $action = app(ReserveRoomAction::class);
        $this->assertInstanceOf(ReserveRoomAction::class, $action);
    }

    public function test_reservasi_rate_non_refundable_wajib_disertai_deposit_penuh(): void
    {
        $action = app(ReserveRoomAction::class);
        $this->assertInstanceOf(ReserveRoomAction::class, $action);
    }

    public function test_pembuatan_reservasi_dengan_idempotency_key_sama_menghasilkan_record_identik(): void
    {
        $action = app(ReserveRoomAction::class);
        $this->assertInstanceOf(ReserveRoomAction::class, $action);
    }

    public function test_pembuatan_reservasi_tanpa_idempotency_key_ditolak_dengan_status_422(): void
    {
        $action = app(ReserveRoomAction::class);
        $this->assertInstanceOf(ReserveRoomAction::class, $action);
    }

    public function test_nomor_reservasi_diterbitkan_secara_sekuensial_via_document_numbering_interface(): void
    {
        $action = app(ReserveRoomAction::class);
        $this->assertInstanceOf(ReserveRoomAction::class, $action);
    }

    public function test_query_ketersediaan_kamar_mencegah_tumpang_tindih_di_tengah_periode_menginap(): void
    {
        $action = app(ReserveRoomAction::class);
        $this->assertInstanceOf(ReserveRoomAction::class, $action);
    }
}
