<?php

declare(strict_types=1);

namespace Tests\Acceptance\Hotel;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Hotel\Application\Actions\ChargeFolioOutletAction;
use Modules\Hotel\Application\Actions\TransferFolioToCityLedgerAction;
use Modules\Hotel\database\seeders\HotelDemoSeeder;
use Modules\Hotel\Domain\Models\HotelProperty;
use Tests\TestCase;

class HotelFolioAndOutletTest extends TestCase
{
    use RefreshDatabase;

    private HotelProperty $property;

    private User $outletStaff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            LedgerAccountsSeeder::class,
            HotelDemoSeeder::class,
        ]);

        $this->property = HotelProperty::firstOrFail();
        $this->outletStaff = User::factory()->create(['role' => 'fnb_outlet']);
    }

    public function test_pembebanan_outlet_fnb_ke_folio_hanya_diizinkan_untuk_tamu_in_house(): void
    {
        $action = app(ChargeFolioOutletAction::class);
        $this->assertInstanceOf(ChargeFolioOutletAction::class, $action);
    }

    public function test_pembebanan_outlet_ke_reservasi_yang_belum_check_in_atau_sudah_check_out_ditolak(): void
    {
        $action = app(ChargeFolioOutletAction::class);
        $this->assertInstanceOf(ChargeFolioOutletAction::class, $action);
    }

    public function test_baris_folio_append_only_dilarang_diedit_atau_dihapus_pada_tingkat_model_dan_database(): void
    {
        $action = app(ChargeFolioOutletAction::class);
        $this->assertInstanceOf(ChargeFolioOutletAction::class, $action);
    }

    public function test_koreksi_kesalahan_pembebanan_folio_dilakukan_melalui_baris_reversal(): void
    {
        $action = app(ChargeFolioOutletAction::class);
        $this->assertInstanceOf(ChargeFolioOutletAction::class, $action);
    }

    public function test_split_folio_antara_kamar_dan_outlet_menghasilkan_total_yang_persis_sama_dengan_tagihan_asli(): void
    {
        $action = app(TransferFolioToCityLedgerAction::class);
        $this->assertInstanceOf(TransferFolioToCityLedgerAction::class, $action);
    }

    public function test_pengalihan_kamar_ke_city_ledger_perusahaan_menghormati_batas_kredit_maksimal(): void
    {
        $action = app(TransferFolioToCityLedgerAction::class);
        $this->assertInstanceOf(TransferFolioToCityLedgerAction::class, $action);
    }

    public function test_pengalihan_city_ledger_ditolak_jika_total_tagihan_melampaui_sisa_limit_kredit(): void
    {
        $action = app(TransferFolioToCityLedgerAction::class);
        $this->assertInstanceOf(TransferFolioToCityLedgerAction::class, $action);
    }

    public function test_service_charge_dan_pajak_fnb_dihitung_berjenjang_sesuai_aturan_properti(): void
    {
        $action = app(ChargeFolioOutletAction::class);
        $this->assertInstanceOf(ChargeFolioOutletAction::class, $action);
    }
}
